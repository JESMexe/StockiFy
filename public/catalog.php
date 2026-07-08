<?php
/**
 * catalog.php
 * ============================================================
 * Página pública del Catálogo Online.
 * Carga los productos de forma dinámica mediante fetch y permite
 * ver detalles, filtrar por categorías y consultar por WhatsApp.
 * ============================================================
 */
require_once __DIR__ . '/../vendor/autoload.php';
use App\core\Database;

$slug = trim($_GET['slug'] ?? '');

if (empty($slug) || !preg_match('/^[a-z0-9][a-z0-9\-]{0,98}$/', $slug)) {
    http_response_code(404);
    render404();
    exit;
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare(
        "SELECT i.name, i.catalog_settings, i.catalog_active
         FROM inventories i
         WHERE i.catalog_slug = ? AND i.catalog_active = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        http_response_code(404);
        render404();
        exit;
    }

    $businessName = htmlspecialchars($inventory['name'], ENT_QUOTES, 'UTF-8');
    $catalogSettings = json_decode($inventory['catalog_settings'], true) ?? [];
    $whatsapp = htmlspecialchars($catalogSettings['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8');
    $instagram = htmlspecialchars($catalogSettings['instagram'] ?? '', ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars($catalogSettings['address'] ?? '', ENT_QUOTES, 'UTF-8');
    $logoUrl = htmlspecialchars($catalogSettings['logo_url'] ?? '', ENT_QUOTES, 'UTF-8');
    
    $btnText = htmlspecialchars($catalogSettings['button_text'] ?? 'Consultar', ENT_QUOTES, 'UTF-8');
    if (empty($btnText)) $btnText = 'Consultar';
    $btnLink = $catalogSettings['button_link'] ?? '';
    $btnIcon = htmlspecialchars($catalogSettings['button_icon'] ?? 'ph-whatsapp-logo', ENT_QUOTES, 'UTF-8');
    $btnColor = htmlspecialchars($catalogSettings['button_color'] ?? 'whatsapp-green', ENT_QUOTES, 'UTF-8');
    
    $themeColor = htmlspecialchars($catalogSettings['theme_color'] ?? 'accent-color', ENT_QUOTES, 'UTF-8');
    $themePattern = htmlspecialchars($catalogSettings['theme_pattern'] ?? 'dots', ENT_QUOTES, 'UTF-8');

    // Colorimetría
    $colorBg      = htmlspecialchars($catalogSettings['color_bg']      ?? '#F4F4F6', ENT_QUOTES, 'UTF-8');
    $colorPattern = htmlspecialchars($catalogSettings['color_pattern'] ?? 'rgba(0,0,0,0.08)', ENT_QUOTES, 'UTF-8');
    $colorCard    = htmlspecialchars($catalogSettings['color_card']    ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $colorAccent  = $catalogSettings['color_accent'] ?? 'theme'; // 'theme' means use --accent-color
    $colorLabel   = htmlspecialchars($catalogSettings['color_label']   ?? '#8A8A8A', ENT_QUOTES, 'UTF-8');
    $colorTitle   = htmlspecialchars($catalogSettings['color_title']   ?? '#1A1A1A', ENT_QUOTES, 'UTF-8');
    $colorPrice   = htmlspecialchars($catalogSettings['color_price']   ?? '#1A1A1A', ENT_QUOTES, 'UTF-8');
    $colorHeaderBg = htmlspecialchars($catalogSettings['color_header_bg'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $colorSocialBg = htmlspecialchars($catalogSettings['color_social_bg'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $colorBadgeBg  = htmlspecialchars($catalogSettings['color_badge_bg']  ?? '#A3BE8C', ENT_QUOTES, 'UTF-8');

    // Shadows
    $shadowFilter = $catalogSettings['shadow_filter_section'] ?? true;
    $shadowPill   = $catalogSettings['shadow_category_pill']  ?? true;
    $shadowCard   = $catalogSettings['shadow_product_card']   ?? true;
    $shadowModal  = $catalogSettings['shadow_modal']          ?? true;

    // Font
    $fontFamily   = htmlspecialchars($catalogSettings['font_family']   ?? 'Outfit', ENT_QUOTES, 'UTF-8');
} catch (Exception $e) {
    error_log("catalog.php error: " . $e->getMessage());
    http_response_code(500);
    echo "Error interno del servidor.";
    exit;
}

function render404() {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Catálogo No Encontrado | StockiFy</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />
        <script src="/assets/js/theme.js"></script>
        <style>
            :root {
                --bg: #F4F4F6;
                --text: #1A1A1A;
                --text-muted: #5A5A5A;
                --accent: #EBCB8B; /* Yellow */
                --accent-blue: #88C0D0; /* Blue */
                --border-strong: 3px solid #1A1A1A;
                --border-soft: 2px solid #1A1A1A;
                --shadow: 8px 8px 0px #1A1A1A;
                --shadow-hover: 10px 10px 0px #1A1A1A;
                --shadow-btn: 4px 4px 0px #1A1A1A;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Outfit', sans-serif;
                background-color: var(--bg);
                background-image: radial-gradient(rgba(26,26,26,0.08) 1.5px, transparent 1.5px);
                background-size: 24px 24px;
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
                text-align: center;
            }
            .container {
                max-width: 480px;
                width: 100%;
                padding: 40px 30px;
                background: #FFFFFF;
                border: var(--border-strong);
                border-radius: 16px;
                box-shadow: var(--shadow);
                position: relative;
            }
            .icon-badge {
                width: 72px;
                height: 72px;
                background: #BF616A; /* Red Accent */
                color: #FFFFFF;
                border: var(--border-strong);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                margin: 0 auto 24px;
                box-shadow: 4px 4px 0px #1A1A1A;
                transform: rotate(-3deg);
            }
            h1 {
                font-size: 28px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: -0.5px;
                margin-bottom: 12px;
                line-height: 1.1;
            }
            p {
                color: var(--text-muted);
                font-size: 15px;
                font-weight: 600;
                line-height: 1.5;
                margin-bottom: 30px;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 28px;
                background-color: var(--accent-color, #88C0D0);
                color: var(--text);
                text-decoration: none;
                font-weight: 600;
                font-size: 15px;
                text-transform: uppercase;
                border: var(--border-strong);
                border-radius: 8px;
                box-shadow: none;
                transition: transform 0.1s ease, box-shadow 0.1s ease;
                cursor: pointer;
            }
            .btn:hover {
                transform: translate(-3px, -3px);
                box-shadow: 4px 4px 0px #1A1A1A;
            }
            .btn:active {
                transform: translate(1px, 1px);
                box-shadow: 1px 1px 0px #1A1A1A;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon-badge"><i class="ph-bold ph-warning-octagon"></i></div>
            <h1>Catálogo No Encontrado</h1>
            <p>El catálogo que estás intentando abrir no existe, ha sido desactivado o la dirección es incorrecta.</p>
            <a href="https://stockify.com.ar" class="btn"><i class="ph-bold ph-house"></i> Volver a StockiFy</a>
        </div>
    </body>
    </html>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Optimization -->
    <title><?= $businessName ?> | Catálogo de Productos</title>
    <meta name="description" content="Explorá el catálogo online de <?= $businessName ?>. Consultas directas por WhatsApp.">
    <meta property="og:title" content="<?= $businessName ?> | Catálogo Online">
    <meta property="og:description" content="Mirá nuestros productos disponibles y hacenos tu consulta directa por WhatsApp.">
    <?php if (!empty($logoUrl)): ?>
        <meta property="og:image" content="<?= $logoUrl ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">

    <!-- Fonts & Icons -->
    <?php
    $fontMap = [
        'Outfit' => 'Outfit:wght@300;400;500;600;700;800',
        'Inter' => 'Inter:wght@300;400;500;600;700;800',
        'Lexend' => 'Lexend:wght@300;400;500;600;700;800',
        'Space Grotesk' => 'Space+Grotesk:wght@400;500;600;700',
        'Syne' => 'Syne:wght@400;600;800',
        'Poppins' => 'Poppins:wght@300;400;500;600;700;800',
        'Montserrat' => 'Montserrat:wght@300;400;500;600;700;800',
        'Playfair Display' => 'Playfair+Display:ital,wght@0,400;0,700;1,400',
        'Courier Prime' => 'Courier+Prime:wght@400;700'
    ];
    $googleFontQuery = $fontMap[$fontFamily] ?? 'Outfit:wght@300;400;500;600;700;800';
    ?>
    <link href="https://fonts.googleapis.com/css2?family=<?= $googleFontQuery ?>&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" href="/assets/css/catalog.css?v=<?= time() ?>">
    <?php
    // Build colorimetry CSS vars inline
    $accentVarVal = ($colorAccent !== 'theme') ? htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') : null;
    ?>
    <style>
        :root {
            --catalog-bg-color:    <?= $colorBg ?>;
            --catalog-card-bg:     <?= $colorCard ?>;
            --catalog-label-color: <?= $colorLabel ?>;
            --catalog-title-color: <?= $colorTitle ?>;
            --catalog-price-color: <?= $colorPrice ?>;
            --catalog-header-bg:   <?= $colorHeaderBg ?>;
            --catalog-social-bg:   <?= $colorSocialBg ?>;
            --catalog-badge-bg:    <?= $colorBadgeBg ?>;
            --font-family:         '<?= $fontFamily ?>', sans-serif;
            <?php if ($accentVarVal): ?>
            --catalog-accent:      <?= $accentVarVal ?>;
            <?php endif; ?>
        }

        .business-header { background-color: var(--catalog-header-bg); }
        .contact-btn { background-color: var(--catalog-social-bg); }
        .badge-stock { background-color: var(--catalog-badge-bg); }

        <?php if (!$shadowPill): ?>
        .category-pill, .btn-load-more { box-shadow: none !important; transform: none !important; }
        .category-pill:hover, .btn-load-more:hover { box-shadow: none !important; transform: none !important; }
        <?php endif; ?>

        <?php if (!$shadowCard): ?>
        .product-card { box-shadow: none !important; }
        .product-card:hover { transform: none !important; }
        <?php endif; ?>

        <?php if (!$shadowModal): ?>
        .modal-content, .modal-close-btn { box-shadow: none !important; }
        .modal-close-btn:hover { transform: none !important; }
        <?php endif; ?>

        <?php if (!$shadowFilter): ?>
        .filter-section { box-shadow: none !important; }
        <?php endif; ?>
    </style>
    <script src="/assets/js/theme.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pattern = '<?= $themePattern ?>';
            const patternColor = '<?= $colorPattern ?>';
            let bgImage = 'none';
            if (pattern === 'dots') bgImage = `radial-gradient(${patternColor} 1.5px, transparent 1.5px)`;
            else if (pattern === 'grid') bgImage = `linear-gradient(${patternColor} 1px, transparent 1px), linear-gradient(90deg, ${patternColor} 1px, transparent 1px)`;
            else if (pattern === 'lines') bgImage = `repeating-linear-gradient(45deg, ${patternColor} 0, ${patternColor} 2px, transparent 2px, transparent 12px)`;
            
            document.body.style.backgroundImage = bgImage;
            if (pattern === 'grid' || pattern === 'dots') {
                document.body.style.backgroundSize = '24px 24px';
            } else {
                document.body.style.backgroundSize = 'auto';
            }
            
            const selectedThemeColor = '<?= $themeColor ?>';
            if (selectedThemeColor !== 'accent-color') {
                const colorValue = getComputedStyle(document.documentElement).getPropertyValue('--' + selectedThemeColor).trim();
                if (colorValue) {
                    document.documentElement.style.setProperty('--accent-color', colorValue);
                    <?php if ($colorAccent === 'theme'): ?>
                    // If accent is 'theme', it follows --accent-color
                    document.documentElement.style.setProperty('--catalog-accent', colorValue);
                    <?php endif; ?>
                }
            }
        });
    </script>
</head>
<body>

    <!-- Header del Negocio -->
    <header class="business-header">
        <div class="header-container">
            <div class="business-info">
                <?php if (!empty($logoUrl)): ?>
                    <?php 
                        $normalizedLogo = $logoUrl;
                        if (!str_starts_with($normalizedLogo, 'http://') && !str_starts_with($normalizedLogo, 'https://') && !str_starts_with($normalizedLogo, '/')) {
                            $normalizedLogo = '/' . $normalizedLogo;
                        }
                    ?>
                    <img class="business-logo" src="<?= htmlspecialchars($normalizedLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de <?= $businessName ?>">
                <?php else: ?>
                    <div class="business-logo-placeholder">
                        <?= mb_substr($businessName, 0, 1, 'UTF-8') ?>
                    </div>
                    <div class="business-meta">
                        <h1><?= $businessName ?></h1>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="business-contacts">
                <?php if (!empty($address)): ?>
                    <a href="https://maps.google.com/?q=<?= urlencode($address) ?>" target="_blank" class="contact-btn maps-contact" title="Ver dirección: <?= $address ?>">
                        <i class="ph ph-map-pin"></i>
                    </a>
                <?php endif; ?>
                <?php if (!empty($instagram)): ?>
                    <?php 
                        $instaUser = ltrim(trim($instagram), '@');
                    ?>
                    <a href="https://instagram.com/<?= $instaUser ?>" target="_blank" class="contact-btn instagram-contact" title="Ver Instagram: @<?= $instaUser ?>">
                        <i class="ph ph-instagram-logo"></i>
                    </a>
                <?php endif; ?>
                <?php if (!empty($whatsapp)): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>" target="_blank" class="contact-btn whatsapp-contact" title="Enviar WhatsApp: <?= $whatsapp ?>">
                        <i class="ph ph-whatsapp-logo"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="catalog-main">
        
        <!-- Buscador y Filtros -->
        <section class="filter-section">
            <div class="search-box-wrapper">
                <i class="ph ph-magnifying-glass search-icon"></i>
                <input type="text" id="search-input" class="search-input" placeholder="Buscar productos por nombre..." autocomplete="off">
            </div>
            
            <div class="categories-container" id="categories-container">
                <!-- Pills de categorías (se inyectan con JS) -->
            </div>
        </section>

        <!-- Grilla de Productos -->
        <section class="products-grid" id="products-grid">
            <!-- Skeletons iniciales -->
            <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="skeleton-card">
                    <div class="skeleton-img">
                        <div class="shimmer"></div>
                    </div>
                    <div class="skeleton-body">
                        <div class="skeleton-line short"><div class="shimmer"></div></div>
                        <div class="skeleton-line"><div class="shimmer"></div></div>
                        <div class="skeleton-line medium"><div class="shimmer"></div></div>
                        <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px;">
                            <div class="skeleton-line tall"><div class="shimmer"></div></div>
                            <div class="skeleton-button"><div class="shimmer"></div></div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </section>

        <!-- Botón Cargar Más -->
        <div class="pagination-container" id="pagination-container" style="display: none;">
            <button class="btn-load-more" id="btn-load-more">
                <i class="ph ph-plus-circle"></i> Ver más productos
            </button>
        </div>

    </main>

    <!-- Footer -->
    <footer class="catalog-footer">
        <a class="footer-logo-link" href="https://stockify.com.ar" target="_blank">
            <span>Catálogo creado automáticamente con</span>
            <img src="/assets/img/LogoE2.png" alt="StockiFy Logo">
        </a>
    </footer>

    <!-- Modal de Detalle de Producto -->
    <div class="modal-overlay" id="product-modal">
        <div class="modal-content">
            <button class="modal-close-btn" id="modal-close-btn" aria-label="Cerrar modal">✕</button>
            <div class="modal-body">
                <div class="modal-img-container" id="modal-img-container">
                    <!-- Imagen de producto o placeholder -->
                </div>
                <div class="modal-info">
                    <div class="modal-meta">
                        <span class="modal-category" id="modal-category">CATEGORIA</span>
                        <span class="modal-stock-tag" id="modal-stock-tag">STOCK</span>
                    </div>
                    <h2 class="modal-title" id="modal-title">Nombre del Producto</h2>
                    <div class="modal-price-container" id="modal-price-container">
                        <p class="modal-price-label">Precio</p>
                        <p class="modal-price" id="modal-price">$0</p>
                    </div>
                    <div id="modal-extra-details" class="modal-extra-details" style="display: none;"></div>
                    <div class="modal-actions" id="modal-actions">
                        <!-- Botón WhatsApp dinámico -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Frontend Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const SLUG = '<?= $slug ?>';
            const PHONE = '<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>';
            const BUSINESS_NAME = '<?= addslashes($businessName) ?>';
            
            const BTN_TEXT = '<?= addslashes($btnText) ?>';
            const BTN_LINK = <?= json_encode($btnLink) ?>;
            const BTN_ICON = '<?= addslashes($btnIcon) ?>';
            const BTN_COLOR = '<?= addslashes($btnColor) ?>';
            
            // Estado local de la app
            let currentPage = 1;
            let currentSearch = '';
            let currentCategory = '';
            let totalPages = 1;
            let isLoading = false;
            let productsData = []; // Caché de productos cargados
            
            // Elementos del DOM
            const productsGrid = document.getElementById('products-grid');
            const searchInput = document.getElementById('search-input');
            const categoriesContainer = document.getElementById('categories-container');
            const paginationContainer = document.getElementById('pagination-container');
            const btnLoadMore = document.getElementById('btn-load-more');
            
            // Modal
            const productModal = document.getElementById('product-modal');
            const modalCloseBtn = document.getElementById('modal-close-btn');
            const modalImgContainer = document.getElementById('modal-img-container');
            const modalCategory = document.getElementById('modal-category');
            const modalStockTag = document.getElementById('modal-stock-tag');
            const modalTitle = document.getElementById('modal-title');
            const modalPrice = document.getElementById('modal-price');
            const modalActions = document.getElementById('modal-actions');

            // Cargar datos iniciales
            fetchCatalog(true);

            // Event Listeners
            let searchTimeout = null;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentSearch = e.target.value.trim();
                    currentPage = 1;
                    fetchCatalog(true);
                }, 400); // Debounce de 400ms
            });

            btnLoadMore.addEventListener('click', () => {
                if (currentPage < totalPages && !isLoading) {
                    currentPage++;
                    fetchCatalog(false);
                }
            });

            // Cerrar Modal
            modalCloseBtn.addEventListener('click', closeModal);
            productModal.addEventListener('click', (e) => {
                if (e.target === productModal) closeModal();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && productModal.classList.contains('active')) closeModal();
            });

            // Función para traer datos del catálogo
            function fetchCatalog(reset = false) {
                if (isLoading) return;
                isLoading = true;

                if (reset) {
                    productsGrid.innerHTML = getSkeletonsHTML();
                    paginationContainer.style.display = 'none';
                    if (reset && !currentCategory) {
                        // Solo resetear productos si cambiamos filtros gruesos
                        productsData = [];
                    }
                } else {
                    btnLoadMore.disabled = true;
                    btnLoadMore.innerHTML = '<i class="ph ph-spinner-gap" style="animation: spin 1s infinite linear;"></i> Cargando...';
                }

                const url = `/api/catalog/get-catalog.php?slug=${encodeURIComponent(SLUG)}&page=${currentPage}&q=${encodeURIComponent(currentSearch)}&cat=${encodeURIComponent(currentCategory)}`;

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        isLoading = false;
                        btnLoadMore.disabled = false;
                        btnLoadMore.innerHTML = '<i class="ph ph-plus-circle"></i> Ver más productos';

                        if (!data.success) {
                            showErrorState(data.error || 'Error al obtener catálogo.');
                            return;
                        }

                        window.catalogBusiness = data.business || {};
                        totalPages = data.pagination.total_pages;
                        const products = data.products || [];
                        const keys = data.column_map || { name: 'name', stock: 'stock', cat: 'categoria', img: 'imagen_url' };
                        const showPrice = data.business.show_price !== false;
                        window.SHOW_ACTION_BUTTON = data.business.show_action_button !== false;

                        if (reset) {
                            productsGrid.innerHTML = '';
                            productsData = [];
                        }

                        if (products.length === 0 && currentPage === 1) {
                            showEmptyState();
                            return;
                        }

                        // Agregar a caché local
                        productsData = productsData.concat(products);

                        // Renderizar productos
                        products.forEach(product => {
                            productsGrid.appendChild(createProductCard(product, keys, showPrice));
                        });

                        // Renderizar categorías si es el reset inicial
                        if (reset && data.categories) {
                            renderCategories(data.categories);
                        }

                        // Manejar paginación
                        if (currentPage < totalPages) {
                            paginationContainer.style.display = 'flex';
                        } else {
                            paginationContainer.style.display = 'none';
                        }
                    })
                    .catch(err => {
                        isLoading = false;
                        btnLoadMore.disabled = false;
                        btnLoadMore.innerHTML = '<i class="ph ph-plus-circle"></i> Ver más productos';
                        showErrorState('Ocurrió un problema de conexión.');
                        console.error('Fetch catalog error:', err);
                    });
            }

            // Renderizar la barra horizontal de categorías
            function renderCategories(categories) {
                categoriesContainer.innerHTML = '';
                
                // Pill de "Todos"
                const allPill = document.createElement('button');
                allPill.className = `category-pill ${!currentCategory ? 'active' : ''}`;
                allPill.textContent = 'Todos';
                allPill.onclick = () => selectCategory('');
                categoriesContainer.appendChild(allPill);

                categories.forEach(cat => {
                    const pill = document.createElement('button');
                    pill.className = `category-pill ${currentCategory === cat ? 'active' : ''}`;
                    pill.textContent = cat;
                    pill.onclick = () => selectCategory(cat);
                    categoriesContainer.appendChild(pill);
                });
            }

            function selectCategory(cat) {
                if (currentCategory === cat) return;
                currentCategory = cat;
                currentPage = 1;
                
                // Actualizar clase activa en UI inmediatamente para mejor feedback táctil
                Array.from(categoriesContainer.children).forEach(pill => {
                    if (pill.textContent === (cat || 'Todos')) {
                        pill.classList.add('active');
                    } else {
                        pill.classList.remove('active');
                    }
                });

                fetchCatalog(true);
            }

            // Crear tarjeta de producto
            function createProductCard(product, keys, showPrice) {
                const card = document.createElement('article');
                card.className = 'product-card';
                
                const name = product[keys.name] || 'Sin Nombre';
                const stockVal = parseFloat(product[keys.stock] ?? 0);
                const isAvailable = stockVal > 0 || product[keys.stock + '_display'] === 'Disponible';
                const category = product[keys.cat] || '';
                const imgUrl = product[keys.img] || '';
                
                let formattedImgUrl = imgUrl;
                if (imgUrl && !imgUrl.startsWith('http://') && !imgUrl.startsWith('https://') && !imgUrl.startsWith('/')) {
                    formattedImgUrl = '/' + imgUrl;
                }

                const price = getProductPrice(product, keys);

                let priceHTML = '';
                if (showPrice) {
                    priceHTML = `<span class="product-price">${formatCurrency(price)}</span>`;
                }

                let badgeHTML = '';
                if (!isAvailable) {
                    badgeHTML = `<span class="product-badge badge-out-of-stock">Sin Stock</span>`;
                } else {
                    badgeHTML = `<span class="product-badge badge-stock">Disponible</span>`;
                }

                // Placeholder / Imagen
                let imgHTML = '';
                if (imgUrl) {
                    imgHTML = `<img src="${formattedImgUrl}" alt="${name}" class="product-image" loading="lazy">`;
                } else {
                    imgHTML = `
                        <div class="product-image-placeholder">
                            <i class="ph ph-package"></i>
                            <span>Sin Imagen</span>
                        </div>
                    `;
                }

                let actionBtnHtml = '';
                if (window.SHOW_ACTION_BUTTON) {
                    let actionFunc = '';
                    if (BTN_LINK) {
                        const finalLink = BTN_LINK.replace(/\[PRODUCTO\]/g, encodeURIComponent(name));
                        actionFunc = `window.open('${finalLink}', '_blank')`;
                    } else {
                        actionFunc = `shareToWhatsapp('${encodeURIComponent(name)}')`;
                    }
                    
                    actionBtnHtml = `
                        <button class="btn-whatsapp-action" style="background: var(--${BTN_COLOR});" onclick="event.stopPropagation(); ${actionFunc}">
                            <i class="ph ${BTN_ICON}"></i> ${BTN_TEXT}
                        </button>
                    `;
                }

                card.innerHTML = `
                    <div class="product-image-container">
                        ${badgeHTML}
                        ${imgHTML}
                    </div>
                    <div class="product-details">
                        <span class="product-category">${category || 'General'}</span>
                        <h3 class="product-title">${name}</h3>
                        <div class="product-footer">
                            ${priceHTML}
                            ${actionBtnHtml}
                        </div>
                    </div>
                `;

                // Clic en la tarjeta abre el Modal de detalles
                card.addEventListener('click', () => openModal(product, keys, showPrice));

                return card;
            }

            // Abrir Modal de Producto
            function openModal(product, keys, showPrice) {
                const name = product[keys.name] || 'Sin Nombre';
                const category = product[keys.cat] || 'General';
                const imgUrl = product[keys.img] || '';
                
                let formattedImgUrl = imgUrl;
                if (imgUrl && !imgUrl.startsWith('http://') && !imgUrl.startsWith('https://') && !imgUrl.startsWith('/')) {
                    formattedImgUrl = '/' + imgUrl;
                }

                const price = getProductPrice(product, keys);
                
                // Determinar stock
                let stockText = 'Sin stock';
                let stockClass = 'badge-out-of-stock';
                if (product[keys.stock + '_display']) {
                    stockText = product[keys.stock + '_display'];
                    stockClass = stockText === 'Disponible' ? 'badge-stock' : 'badge-out-of-stock';
                } else {
                    const stockVal = parseFloat(product[keys.stock] ?? 0);
                    stockText = stockVal > 0 ? `Stock: ${stockVal} u.` : 'Sin stock';
                    stockClass = stockVal > 0 ? 'badge-stock' : 'badge-out-of-stock';
                }

                // Imagen modal
                if (imgUrl) {
                    modalImgContainer.innerHTML = `<img src="${formattedImgUrl}" alt="${name}" class="modal-img">`;
                } else {
                    modalImgContainer.innerHTML = `
                        <div class="modal-img-placeholder">
                            <i class="ph ph-package"></i>
                        </div>
                    `;
                }

                modalCategory.textContent = category.toUpperCase();
                modalStockTag.textContent = stockText;
                modalStockTag.className = `modal-stock-tag ${stockClass}`;
                modalTitle.textContent = name;

                if (showPrice) {
                    modalPrice.textContent = formatCurrency(price);
                    modalPrice.className = 'modal-price';
                    document.getElementById('modal-price-container').style.display = 'block';
                } else {
                    document.getElementById('modal-price-container').style.display = 'none';
                }

                // Columnas extras
                const extraDetailsContainer = document.getElementById('modal-extra-details');
                if (extraDetailsContainer) {
                    extraDetailsContainer.innerHTML = '';
                    let hasAnyExtra = false;

                    const extraCols = [
                        window.catalogBusiness?.extra_column_1,
                        window.catalogBusiness?.extra_column_2,
                        window.catalogBusiness?.extra_column_3
                    ];

                    extraCols.forEach(colName => {
                        if (colName && product[colName] !== undefined && product[colName] !== null && product[colName].toString().trim() !== '') {
                            const detailItem = document.createElement('div');
                            detailItem.className = 'extra-detail-item';

                            const label = document.createElement('span');
                            label.className = 'extra-detail-label';
                            label.textContent = colName;

                            const value = document.createElement('span');
                            value.className = 'extra-detail-value';
                            value.textContent = product[colName];

                            detailItem.appendChild(label);
                            detailItem.appendChild(value);
                            extraDetailsContainer.appendChild(detailItem);
                            hasAnyExtra = true;
                        }
                    });

                    if (hasAnyExtra) {
                        extraDetailsContainer.style.display = 'flex';
                        if (showPrice) {
                            extraDetailsContainer.style.borderTop = 'var(--border-soft)';
                        } else {
                            extraDetailsContainer.style.borderTop = 'none';
                        }
                    } else {
                        extraDetailsContainer.style.display = 'none';
                    }
                }

                if (window.SHOW_ACTION_BUTTON) {
                    let modalActionBtnHtml = '';
                    let actionFunc = '';
                    if (BTN_LINK) {
                        const finalLink = BTN_LINK.replace(/\[PRODUCTO\]/g, encodeURIComponent(name));
                        actionFunc = `window.open('${finalLink}', '_blank')`;
                    } else {
                        actionFunc = `shareToWhatsapp('${encodeURIComponent(name)}')`;
                    }
                    
                    modalActionBtnHtml = `
                        <button class="btn-whatsapp-action" style="background: var(--${BTN_COLOR});" onclick="${actionFunc}">
                            <i class="ph ${BTN_ICON}"></i> ${BTN_TEXT}
                        </button>
                    `;

                    modalActions.innerHTML = modalActionBtnHtml;
                    modalActions.style.display = 'block';
                } else {
                    modalActions.innerHTML = '';
                    modalActions.style.display = 'none';
                }

                productModal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Evita scroll de fondo
            }

            function closeModal() {
                productModal.classList.remove('active');
                document.body.style.overflow = '';
            }

            // Compartir / Consultar producto por WhatsApp
            window.shareToWhatsapp = function(productNameEncoded) {
                const name = decodeURIComponent(productNameEncoded);
                const text = `Hola! Estoy interesado en el producto "${name}" que vi en tu catálogo online de ${BUSINESS_NAME}. ¿Tienen disponibilidad?`;
                const waUrl = `https://wa.me/${PHONE}?text=${encodeURIComponent(text)}`;
                window.open(waUrl, '_blank');
            };

            function getProductPrice(product, keys) {
                 let rawPrice = product[keys.price] ?? product.sale_price ?? product.precio ?? product['Precio de Venta'] ?? 0;
                 if (typeof rawPrice === 'string') {
                     if (rawPrice.includes(',') && rawPrice.includes('.')) {
                         rawPrice = rawPrice.replace(/\./g, '').replace(/,/g, '.');
                     } else if (rawPrice.includes(',')) {
                         rawPrice = rawPrice.replace(/,/g, '.');
                     }
                     rawPrice = rawPrice.replace(/[^0-9.]/g, '');
                 }
                 return parseFloat(rawPrice) || 0;
             }

            // Formato de moneda
            function formatCurrency(val) {
                return new Intl.NumberFormat('es-AR', {
                    style: 'currency',
                    currency: 'ARS',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(val);
            }

            // Skeletons HTML
            function getSkeletonsHTML() {
                let html = '';
                for (let i = 0; i < 8; i++) {
                    html += `
                        <div class="skeleton-card">
                            <div class="skeleton-img">
                                <div class="shimmer"></div>
                            </div>
                            <div class="skeleton-body">
                                <div class="skeleton-line short"><div class="shimmer"></div></div>
                                <div class="skeleton-line"><div class="shimmer"></div></div>
                                <div class="skeleton-line medium"><div class="shimmer"></div></div>
                                <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px;">
                                    <div class="skeleton-line tall"><div class="shimmer"></div></div>
                                    <div class="skeleton-button"><div class="shimmer"></div></div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                return html;
            }

            // Mostrar estado vacío
            function showEmptyState() {
                productsGrid.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="ph ph-package"></i></div>
                        <h4 class="empty-state-title">No hay productos</h4>
                        <p class="empty-state-desc">No se encontraron productos disponibles que coincidan con tu búsqueda en este momento.</p>
                    </div>
                `;
            }

            // Mostrar estado de error
            function showErrorState(msg) {
                productsGrid.innerHTML = `
                    <div class="empty-state" style="border-color: rgba(239, 68, 68, 0.2);">
                        <div class="empty-state-icon" style="color: #EF4444;"><i class="ph ph-warning-circle"></i></div>
                        <h4 class="empty-state-title">Error al cargar productos</h4>
                        <p class="empty-state-desc">${msg}</p>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
