<?php
/**
 * upload-image.php
 * ============================================================
 * Endpoint de carga de imágenes para el catálogo.
 * Guarda las imágenes físicamente en:
 * public/assets/img/catalog/[OWNER_ID]/[INVENTARIO_ID]/[IMAGEN]
 *
 * POST /public/api/catalog/upload-image.php
 * Body (Multipart Form Data):
 *   - "image": archivo de imagen
 *   - "inventory_id": ID del inventario activo
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

$inventoryId = (int)($_POST['inventory_id'] ?? 0);
if (!$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de inventario es requerido.']);
    exit;
}

// Verificar rol/acceso
$role = getInventoryRole((int)$user['id'], $inventoryId);
if (!$role) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin acceso a este inventario.']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo de imagen válido.']);
    exit;
}

$file = $_FILES['image'];

// Validar tamaño (máximo 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La imagen supera el límite de 5 MB.']);
    exit;
}

// Validar que sea realmente una imagen (Mime-Type)
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo se aceptan JPG, PNG, GIF y WEBP.']);
    exit;
}

// Validar extensión
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($ext, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Extensión de archivo no permitida.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Obtener el user_id del Owner del inventario
    $stmtOwner = $pdo->prepare("SELECT user_id FROM inventories WHERE id = ? LIMIT 1");
    $stmtOwner->execute([$inventoryId]);
    $ownerId = $stmtOwner->fetchColumn();

    if (!$ownerId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Inventario no encontrado.']);
        exit;
    }

    // Definir directorios
    $publicDir = dirname(__DIR__, 2); // public/
    $subPath = "assets/img/catalog/{$ownerId}/{$inventoryId}";
    $targetDir = "{$publicDir}/{$subPath}/";

    // Crear directorio recursivamente si no existe
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de subida.");
        }
    }

    // Nombre de archivo seguro e único
    $filename = uniqid('img_', true) . '.' . $ext;
    $targetFilePath = $targetDir . $filename;

    // Mover archivo temporal al directorio final
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        // Otorgar permisos de lectura por seguridad en Windows
        @chmod($targetFilePath, 0644);

        $relativeUrl = "{$subPath}/{$filename}";
        echo json_encode([
            'success' => true,
            'url'     => $relativeUrl,
            'message' => 'Imagen subida con éxito.'
        ]);
    } else {
        throw new Exception("Error al mover el archivo subido.");
    }

} catch (Exception $e) {
    error_log("upload-image.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno al procesar el archivo.']);
}
