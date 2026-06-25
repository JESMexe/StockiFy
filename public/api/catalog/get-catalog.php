<?php
/**
 * get-catalog.php
 * ============================================================
 * Endpoint PÚBLICO — No requiere sesión.
 * Devuelve los productos visibles de un inventario por slug.
 *
 * GET /api/catalog/get-catalog.php?slug=XYZ
 *      &page=1        (default 1)
 *      &limit=20      (default 20, max 50)
 *      &q=busqueda    (búsqueda por nombre/SKU)
 *      &cat=Categoria (filtro por categoría)
 *
 * Seguridad:
 *  - Solo expone columnas seguras (nunca buy_price, hard_gain, etc.)
 *  - Rate Limiting: máx. 60 peticiones/minuto por IP
 *  - Usa consultas preparadas PDO
 *  - htmlspecialchars en todos los strings de salida
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\core\Database;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// -------------------------------------------------------
// Rate Limiting — máx 60 req/min por IP
// -------------------------------------------------------
$clientIp  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$clientIp  = trim(explode(',', $clientIp)[0]); // Primer IP si hay proxy
$cacheFile = sys_get_temp_dir() . '/stockify_rl_' . md5($clientIp) . '.json';
$now       = time();
$window    = 60;   // segundos
$maxReqs   = 60;

$rlData = ['count' => 0, 'start' => $now];
if (file_exists($cacheFile)) {
    $raw = @json_decode(file_get_contents($cacheFile), true);
    if ($raw && ($now - $raw['start']) < $window) {
        $rlData = $raw;
    }
}

$rlData['count']++;
file_put_contents($cacheFile, json_encode($rlData), LOCK_EX);

if ($rlData['count'] > $maxReqs) {
    http_response_code(429);
    header('Retry-After: ' . ($window - ($now - $rlData['start'])));
    echo json_encode(['success' => false, 'error' => 'Demasiadas peticiones. Intentá nuevamente en un momento.']);
    exit;
}

// -------------------------------------------------------
// Validar slug
// -------------------------------------------------------
$slug = trim($_GET['slug'] ?? '');
if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,98}$/', $slug)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Slug inválido.']);
    exit;
}

// -------------------------------------------------------
// Parámetros de paginación y filtro
// -------------------------------------------------------
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$search = trim($_GET['q']   ?? '');
$catFilter = trim($_GET['cat'] ?? '');

try {
    $pdo = Database::getInstance();

    // -------------------------------------------------------
    // 1. Buscar inventario por slug
    // -------------------------------------------------------
    $stmt = $pdo->prepare(
        "SELECT i.id, i.name, i.user_id, i.preferences,
                i.catalog_settings, i.catalog_active
         FROM inventories i
         WHERE i.catalog_slug = ? AND i.catalog_active = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Catálogo no encontrado o inactivo.', 'code' => 'NOT_FOUND']);
        exit;
    }

    $inventoryId = (int)$inventory['id'];
    $catalogSettings = !empty($inventory['catalog_settings'])
        ? json_decode($inventory['catalog_settings'], true)
        : [];
    $prefs = !empty($inventory['preferences'])
        ? json_decode($inventory['preferences'], true)
        : [];

    // -------------------------------------------------------
    // 2. Obtener nombre de la tabla dinámica
    // -------------------------------------------------------
    $stmtTable = $pdo->prepare("SELECT table_name, columns_json FROM user_tables WHERE inventory_id = ? LIMIT 1");
    $stmtTable->execute([$inventoryId]);
    $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

    if (!$tableRow) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error interno: tabla no encontrada.']);
        exit;
    }

    $tableName = $tableRow['table_name'];
    $safeTable = "`" . str_replace("`", "``", $tableName) . "`";
    $columnsJson = json_decode($tableRow['columns_json'], true) ?? [];

    // -------------------------------------------------------
    // 3. Detectar columnas disponibles de forma segura
    //    Usamos INFORMATION_SCHEMA para saber qué existe realmente
    // -------------------------------------------------------

    // Columnas SIEMPRE seguras de exponer (nunca financieras)
    $blockedColumns = ['buy_price', 'hard_gain', 'percentage_gain', 'receipt_price', 'min_stock'];

    $stmtCols = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?
         ORDER BY ordinal_position"
    );
    $stmtCols->execute([$tableName]);
    $allDbColumns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    // Columnas que sí exponemos: todo lo que no sea financiero privado
    $safeColumns = array_filter($allDbColumns, fn($col) =>
        !in_array(strtolower($col), $blockedColumns)
    );

    // Mapeo dinámico de columnas
    $mapping = $prefs['mapping'] ?? [];
    $nameCol  = $mapping['name']     ?? 'name';
    $stockCol = $mapping['stock']    ?? 'stock';
    $catCol   = $mapping['category'] ?? 'categoria';
    $imgCol   = $mapping['image']    ?? $mapping['images'] ?? 'imagen_url';
    $priceCol = $mapping['sale_price'] ?? 'sale_price';

    // Auto-detección inteligente si las claves no están mapeadas en las preferencias
    if (empty($mapping['name'])) {
        foreach ($allDbColumns as $col) {
            $colLower = strtolower($col);
            if (in_array($colLower, ['nombre', 'producto', 'name', 'desc', 'descripcion', 'art', 'articulo'])) {
                $nameCol = $col;
                break;
            }
        }
    }
    if (empty($mapping['stock'])) {
        foreach ($allDbColumns as $col) {
            $colLower = strtolower($col);
            if (in_array($colLower, ['stock', 'cantidad', 'cant', 'existencias', 'disp', 'disponible'])) {
                $stockCol = $col;
                break;
            }
        }
    }
    if (empty($mapping['category'])) {
        foreach ($allDbColumns as $col) {
            $colLower = strtolower($col);
            if (in_array($colLower, ['categoria', 'category', 'rubro', 'tipo'])) {
                $catCol = $col;
                break;
            }
        }
    }
    if (empty($mapping['image']) && empty($mapping['images'])) {
        foreach ($allDbColumns as $col) {
            $colLower = strtolower($col);
            if (in_array($colLower, ['imagen_url', 'imagen', 'image', 'images', 'foto', 'url_imagen'])) {
                $imgCol = $col;
                break;
            }
        }
    }
    if (empty($mapping['sale_price'])) {
        foreach ($allDbColumns as $col) {
            $colLower = strtolower($col);
            if (in_array($colLower, ['precio de venta', 'precio venta', 'precio_venta', 'precio', 'price', 'sale_price', 'valor', 'venta'])) {
                $priceCol = $col;
                break;
            }
        }
    }

    // Construir SELECT de columnas seguras
    $selectParts = [];
    foreach ($safeColumns as $col) {
        $selectParts[] = "`" . str_replace("`", "``", $col) . "`";
    }
    $selectSql = implode(', ', $selectParts) ?: 'id, public_visible';

    // -------------------------------------------------------
    // 4. Construir query con filtros
    // -------------------------------------------------------
    $whereClauses = ['public_visible = 1'];
    $params = [];

    if (!empty($search)) {
        $safeNameCol = "`" . str_replace("`", "``", $nameCol) . "`";
        $whereClauses[] = "({$safeNameCol} LIKE ? OR `id` LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    // Filtro por categoría (si la columna existe en la tabla)
    $hasCatCol = in_array($catCol, $allDbColumns);
    if (!empty($catFilter) && $hasCatCol) {
        $safeCatCol = "`" . str_replace("`", "``", $catCol) . "`";
        $whereClauses[] = "{$safeCatCol} = ?";
        $params[] = $catFilter;
    }

    $whereStr = 'WHERE ' . implode(' AND ', $whereClauses);

    // Count total para paginación
    $countSql = "SELECT COUNT(*) FROM {$safeTable} {$whereStr}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Query de productos
    $sql = "SELECT {$selectSql} FROM {$safeTable} {$whereStr} ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmtProd = $pdo->prepare($sql);
    $stmtProd->execute($params);
    $rawProducts = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------
    // 5. Sanitizar outputs para prevenir XSS
    // -------------------------------------------------------
    $showExactStock = $catalogSettings['show_exact_stock'] ?? true;

    $products = array_map(function ($prod) use ($showExactStock, $stockCol, $blockedColumns) {
        $clean = [];
        foreach ($prod as $key => $value) {
            // Doble verificación: nunca exponer columnas financieras
            if (in_array(strtolower($key), $blockedColumns)) continue;

            // Sanitizar string para XSS
            $clean[$key] = is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        }

        // Transformar stock según configuración
        if (isset($clean[$stockCol]) && !$showExactStock) {
            $stockVal = (float)($prod[$stockCol] ?? 0);
            $clean[$stockCol . '_display'] = $stockVal > 0 ? 'Disponible' : 'Sin Stock';
            unset($clean[$stockCol]);
        }

        return $clean;
    }, $rawProducts);

    // -------------------------------------------------------
    // 6. Obtener categorías disponibles (para filtros del frontend)
    // -------------------------------------------------------
    $categories = [];
    if ($hasCatCol) {
        $safeCatCol = "`" . str_replace("`", "``", $catCol) . "`";
        $stmtCats = $pdo->prepare(
            "SELECT DISTINCT {$safeCatCol} FROM {$safeTable}
             WHERE public_visible = 1 AND {$safeCatCol} IS NOT NULL AND {$safeCatCol} != ''
             ORDER BY {$safeCatCol} ASC LIMIT 50"
        );
        $stmtCats->execute();
        $categories = array_map(
            fn($c) => htmlspecialchars($c, ENT_QUOTES, 'UTF-8'),
            $stmtCats->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    // -------------------------------------------------------
    // 7. Info pública del negocio (sanitizada)
    // -------------------------------------------------------
    $businessInfo = [
        'name'             => htmlspecialchars($inventory['name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'whatsapp'         => htmlspecialchars($catalogSettings['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8'),
        'instagram'        => htmlspecialchars($catalogSettings['instagram'] ?? '', ENT_QUOTES, 'UTF-8'),
        'address'          => htmlspecialchars($catalogSettings['address'] ?? '', ENT_QUOTES, 'UTF-8'),
        'logo_url'         => htmlspecialchars($catalogSettings['logo_url'] ?? '', ENT_QUOTES, 'UTF-8'),
        'show_exact_stock' => (bool)($catalogSettings['show_exact_stock'] ?? true),
        'show_price'       => (bool)($catalogSettings['show_price'] ?? true),
        'show_action_button' => isset($catalogSettings['show_action_button']) ? (bool)$catalogSettings['show_action_button'] : true,
    ];

    echo json_encode([
        'success'       => true,
        'business'      => $businessInfo,
        'products'      => $products,
        'categories'    => $categories,
        'column_map'    => [
            'name'  => $nameCol,
            'stock' => $stockCol,
            'cat'   => $catCol,
            'img'   => $imgCol,
            'price' => $priceCol,
        ],
        'pagination'    => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    error_log("get-catalog.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
