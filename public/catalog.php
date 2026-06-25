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
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
        <style>
            :root {
                --bg: #0B0F19;
                --text: #F3F4F6;
                --text-muted: #9CA3AF;
                --accent: #3B82F6;
                --accent-hover: #2563EB;
                --card-bg: rgba(255, 255, 255, 0.03);
                --card-border: rgba(255, 255, 255, 0.08);
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Outfit', sans-serif;
                background-color: var(--bg);
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
                text-align: center;
            }
            .container {
                max-width: 500px;
                padding: 40px;
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                border-radius: 24px;
                backdrop-filter: blur(12px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            }
            .icon {
                font-size: 64px;
                color: #EF4444;
                margin-bottom: 20px;
                animation: pulse 2s infinite;
            }
            h1 { font-size: 28px; font-weight: 800; margin-bottom: 12px; }
            p { color: var(--text-muted); font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background-color: var(--accent);
                color: white;
                text-decoration: none;
                font-weight: 600;
                border-radius: 12px;
                transition: all 0.2s ease;
            }
            .btn:hover { background-color: var(--accent-hover); transform: translateY(-2px); }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon"><i class="ph ph-warning-circle"></i></div>
            <h1>Catálogo No Encontrado</h1>
            <p>El catálogo que estás intentando abrir no existe, ha sido desactivado o la dirección es incorrecta.</p>
            <a href="https://stockify.com.ar" class="btn"><i class="ph ph-house"></i> Volver a StockiFy</a>
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" href="/assets/css/catalog.css?v=<?= time() ?>">
    <script src="/assets/js/theme.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pattern = '<?= $themePattern ?>';
            let bgImage = 'none';
            if (pattern === 'dots') bgImage = 'radial-gradient(rgba(0, 0, 0, 0.08) 1.5px, transparent 1.5px)';
            else if (pattern === 'grid') bgImage = 'linear-gradient(rgba(0, 0, 0, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 0, 0, 0.05) 1px, transparent 1px)';
            else if (pattern === 'lines') bgImage = 'repeating-linear-gradient(45deg, rgba(0,0,0,0.03) 0, rgba(0,0,0,0.03) 2px, transparent 2px, transparent 12px)';
            
            document.body.style.backgroundImage = bgImage;
            if (pattern === 'grid') document.body.style.backgroundSize = '24px 24px';
            
            const selectedThemeColor = '<?= $themeColor ?>';
            if (selectedThemeColor !== 'accent-color') {
                const colorValue = getComputedStyle(document.documentElement).getPropertyValue('--' + selectedThemeColor).trim();
                if (colorValue) {
                    document.documentElement.style.setProperty('--accent-color', colorValue);
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
                    <a href="https://maps.google.com/?q=<?= urlencode($address) ?>" target="_blank" class="contact-btn" title="Ver dirección: <?= $address ?>">
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
                        <button class="btn-whatsapp-action" style="background-color: var(--${BTN_COLOR});" onclick="event.stopPropagation(); ${actionFunc}">
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
                        <button class="btn-whatsapp-action" style="background-color: var(--${BTN_COLOR});" onclick="${actionFunc}">
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
