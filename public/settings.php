<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | StockiFy</title>

    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/configuration.css">
    <link rel="stylesheet" href="/assets/css/sweetalert.css">

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="/assets/js/sweetalert2.all.min.js?v=11.0"></script>
    <script>
        if (typeof Swal === 'undefined') {
            console.warn("SweetAlert2 local no pudo cargarse. Cargando fallback desde CDN...");
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }
    </script>
    <script src="/assets/js/theme.js"></script>
    <script type="module" src="/assets/js/configuration.js"></script>
</head>

<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    header('Location: index');
    exit;
}

if (!isset($currentUser['subscription_active']) || $currentUser['subscription_active'] == 0) {
    header('Location: index#section-pricing');
    exit;
}

$activeInventoryId = $_SESSION['active_inventory_id'] ?? null;
$activeInventoryRole = $_SESSION['active_inventory_role'] ?? null;
$canConfigRemito = $activeInventoryId && in_array($activeInventoryRole, ['Owner', 'Admin']);

$remitoLogo = '';
$remitoDescription = '';
$remitoUrl = '';
$inventoryName = '';

// Catalog data
$catalogActive = 0;
$catalogSlug = '';
$catalogSettings = [];

if ($canConfigRemito) {
    $dbInstance = \App\core\Database::getInstance();
    $stmtInv = $dbInstance->prepare("SELECT name, remito_logo_path, remito_description, remito_url, catalog_active, catalog_slug, catalog_settings FROM inventories WHERE id = ?");
    $stmtInv->execute([$activeInventoryId]);
    $invSettings = $stmtInv->fetch(PDO::FETCH_ASSOC);
    if ($invSettings) {
        $inventoryName = $invSettings['name'] ?? '';
        $remitoLogo = $invSettings['remito_logo_path'] ?? '';
        $remitoDescription = $invSettings['remito_description'] ?? '';
        $remitoUrl = $invSettings['remito_url'] ?? '';
        $catalogActive = (int)($invSettings['catalog_active'] ?? 0);
        $catalogSlug = $invSettings['catalog_slug'] ?? '';
        $catalogSettings = !empty($invSettings['catalog_settings'])
            ? json_decode($invSettings['catalog_settings'], true)
            : [];
    }
}
?>

<body id="page-configuration">
    <div id="grey-background" class="hidden"></div>

    <header>
        <a href="dashboard" id="header-logo">
            <img src="assets/img/LogoE.png" alt="Stocky Logo">
        </a>
        <nav id="header-nav">
            <a href="dashboard" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Volver al Dashboard</a>
        </nav>
    </header>

    <main class="text-left">
        <div class="config-layout-wrapper">
            <div id="options-config-container">
                <div class="btn btn-option-selected" id="btn-config-cuenta">
                    <p><i class="ph ph-user-gear"></i> Mi Cuenta</p>
                </div>
                <?php if ($canConfigRemito): ?>
                <div class="btn" id="btn-config-remito">
                    <p><i class="ph ph-receipt"></i> Mi Remito</p>
                </div>
                <div class="btn" id="btn-config-catalogo">
                    <p><i class="ph ph-storefront"></i> Catálogo Online</p>
                </div>
                <?php endif; ?>
                <div class="btn" id="btn-config-soporte">
                    <p><i class="ph ph-lifebuoy"></i> Soporte</p>
                </div>
            </div>

            <div id="config-container">

                <div id="config-container-cuenta">
                    <form class="flex-column" id="form-micuenta">
                        <h3 class="config-section-title">Información del Perfil</h3>

                        <div class="config-grid">
                            <div class="rustic-block">
                                <label class="option-label" for="username">Nombre de Usuario</label>
                                <input class="config-input" type="text" id="username" name="username"
                                    value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>">
                                <p class="unsaved-hint hidden" style="color: var(--accent-color); font-size: 0.8rem; margin-top: 4px; font-weight: 500;">Recordá guardar los cambios</p>
                            </div>
                            <div class="rustic-block">
                                <label class="option-label" for="full_name">Nombre Completo</label>
                                <input class="config-input" type="text" id="full_name" name="full_name"
                                    value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>">
                                <p class="unsaved-hint hidden" style="color: var(--accent-color); font-size: 0.8rem; margin-top: 4px; font-weight: 500;">Recordá guardar los cambios</p>
                            </div>
                            <div class="rustic-block" style="display: none;">
                                <label class="option-label" for="dni">DNI / Identificación</label>
                                <input class="config-input" type="text" id="dni" name="dni"
                                    value="<?php echo htmlspecialchars($currentUser['dni'] ?? ''); ?>"
                                    placeholder="(Colocar sin puntos)">
                            </div>
                            <div class="rustic-block" style="grid-column: 1 / -1;">
                                <label class="option-label">Teléfono / Celular</label>
                                <div class="phone-input-container">
                                    <select class="config-input phone-select" id="cell_country">
                                        <option value="54" selected>+54 (AR)</option>
                                    </select>
                                    <select class="config-input phone-select" id="cell_prefix">
                                        <option value="9" selected>9 (Móvil)</option>
                                    </select>
                                    <input class="config-input phone-number-input" type="text" id="cell_number"
                                        placeholder="(ej: 11 6768-4020)">
                                </div>
                                <span class="phone-hint">
                                    StockiFy tomará el registro del dato del teléfono para enviarle las notificaciones o
                                    alertas del sistema, en caso de no quererlas, ignorar este campo dejándolo vacío; en
                                    caso de sí querer, procure ingresar los datos correctamente.
                                </span>
                                <input type="hidden" id="cell" name="cell"
                                    value="<?php echo htmlspecialchars($currentUser['cell'] ?? ''); ?>">
                                <p class="unsaved-hint hidden" style="color: var(--accent-color); font-size: 0.8rem; margin-top: 4px; font-weight: 500;">Recordá guardar los cambios</p>
                            </div>
                        </div>

                        <h3 class="config-section-title">Seguridad</h3>

                        <div class="rustic-block locked-field" style="margin-bottom: 1.5rem;">
                            <label class="option-label">Email de la cuenta <span class="helper-tag">Identificador
                                    único</span></label>
                            <input class="config-input input-locked" type="email"
                                value="<?php echo $currentUser['email']; ?>" readonly>
                        </div>

                        <div class="rustic-block">
                            <label class="option-label">Contraseña</label>
                            <div class="input-with-lock" style="display: flex; gap: 1rem; align-items: center;">
                                <input class="config-input input-locked" type="text" value="••••••••••••" disabled
                                    style="flex: 1; margin: 0;">
                                <button type="button" class="btn btn-secondary" id="btn-change-password"
                                    style="width: auto; padding: 0 2rem; height: 50px;">
                                    Cambiar
                                </button>
                            </div>
                        </div>

                        <style>
                            #btn-guardar {
                                background-color: #ffffff !important;
                                color: var(--accent-color) !important;
                                border-color: var(--accent-color) !important;
                                box-shadow: none !important;
                                transition: transform 0.2s ease, box-shadow 0.2s ease;
                            }
                            #btn-guardar:hover:not(:disabled) {
                                box-shadow: 4px 4px 0 rgba(0,0,0,0.15) !important;
                                transform: translate(-3px, -3px);
                            }
                            #btn-guardar:active:not(:disabled) {
                                box-shadow: 0 1px 0 rgba(0,0,0,0.1) !important;
                                transform: translate(3px, 3px);
                            }
                            #btn-guardar:disabled {
                                background-color: lightgray !important;
                                color: gray !important;
                                border-color: gray !important;
                            }
                        </style>
                        <button type="submit" class="btn btn-primary" id="btn-guardar" disabled>Guardar Cambios de
                            Perfil</button>
                    </form>
                </div>

                <?php if ($canConfigRemito): ?>
                <div id="remito-container" class="hidden">
                    <form class="flex-column" id="form-remito" enctype="multipart/form-data">
                        <h3 class="config-section-title">Personalización del Remito</h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">
                            Personalizá la cabecera de tus remitos con los datos de tu negocio. Si no colocás nada, estos campos aparecerán vacíos en el remito.
                        </p>

                        <div class="config-grid">
                            <div class="rustic-block" style="grid-column: 1 / -1; display: flex; flex-direction: column; gap: 10px;">
                                <label class="option-label">Logo del Negocio</label>
                                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                    <div id="remito-logo-preview-container" style="width: 150px; height: 80px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; background: #fafafa; border-radius: 8px; overflow: hidden; position: relative;">
                                        <img id="remito-logo-preview" src="" alt="Vista previa" style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;">
                                        <span id="remito-logo-placeholder" style="color: #aaa; font-size: 0.8rem; text-align: center; padding: 5px;">Sin Logo</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <input type="file" id="remito_logo_input" name="remito_logo" accept="image/*" style="display: none;">
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('remito_logo_input').click();" style="margin:0; width: auto; font-size: 0.85rem; padding: 8px 16px;">Seleccionar Imagen</button>
                                        <button type="button" class="btn btn-secondary" id="btn-delete-logo" style="margin:0; width: auto; font-size: 0.85rem; padding: 8px 16px; color: var(--accent-red); border-color: var(--accent-red); display: none;">Eliminar Logo</button>
                                    </div>
                                </div>
                                <p style="font-size: 0.75rem; color: #666; margin: 0;">Formatos recomendados: PNG, JPG, JPEG, WEBP. Relación de aspecto apaisada (ej. 2:1 o 3:1).</p>
                            </div>

                            <div class="rustic-block" style="grid-column: 1 / -1;">
                                <label class="option-label" for="remito_description">Descripción del Negocio / Leyenda</label>
                                <input class="config-input" type="text" id="remito_description" name="remito_description" placeholder="Ej: Venta de indumentaria por mayor y menor." autocomplete="off">
                                <p class="unsaved-hint hidden" style="color: var(--accent-color); font-size: 0.8rem; margin-top: 4px; font-weight: 500;">Recordá guardar los cambios</p>
                            </div>

                            <div class="rustic-block" style="grid-column: 1 / -1;">
                                <label class="option-label" for="remito_url">Sitio Web / Red Social (URL)</label>
                                <input class="config-input" type="text" id="remito_url" name="remito_url" placeholder="Ej: https://www.minegocio.com" autocomplete="off">
                                <p class="unsaved-hint hidden" style="color: var(--accent-color); font-size: 0.8rem; margin-top: 4px; font-weight: 500;">Recordá guardar los cambios</p>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="btn-guardar-remito" disabled style="margin-top: 1.5rem;">Guardar Configuración de Remito</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($canConfigRemito): ?>
                <div id="catalogo-container" class="hidden">
                    <h3 class="config-section-title">Catálogo Online Público</h3>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">
                        Publicá tus productos en internet. Compartí el link por WhatsApp o Instagram y tus clientes podrán ver tu catálogo sin necesidad de iniciar sesión.
                    </p>

                    <form class="flex-column" id="form-catalogo">

                        <!-- Switch Activar/Desactivar -->
                        <div class="catalog-row-block">
                            <div>
                                <label class="option-label">Activar Catálogo Público</label>
                                <p>Cuando está activo, cualquier persona con el link puede ver tus productos publicados.</p>
                            </div>
                            <label class="catalog-toggle" for="catalog_active_switch">
                                <input type="checkbox" id="catalog_active_switch" <?php echo $catalogActive ? 'checked' : ''; ?>>
                                <span class="catalog-toggle-slider"></span>
                            </label>
                        </div>

                        <div id="catalog-settings-body" style="<?php echo $catalogActive ? '' : 'opacity:0.5; pointer-events:none;'; ?>">

                            <!-- Slug / URL -->
                            <div class="rustic-block" style="margin-top:1.5rem;">
                                <label class="option-label" for="catalog_slug">URL del Catálogo <span class="helper-tag">Identificador único</span></label>
                                <div class="slug-input-wrapper">
                                    <span class="slug-prefix">stockify.com.ar/catalogo/</span>
                                    <div style="flex:1; position:relative; height:100%;">
                                        <input type="text" id="catalog_slug"
                                            value="<?php echo htmlspecialchars($catalogSlug); ?>"
                                            placeholder="mi-negocio"
                                            autocomplete="off"
                                            pattern="[a-z0-9\-]+"
                                            style="padding-right:2.5rem;">
                                        <span id="slug-status-icon" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:1.1rem;"></span>
                                    </div>
                                </div>
                                <p id="slug-feedback" style="font-size:0.8rem; margin-top:4px; min-height:1em; margin-bottom:0;"></p>
                                <div id="catalog-url-preview" class="catalog-url-card" style="display:<?php echo $catalogSlug ? 'flex' : 'none'; ?>;">
                                    <i class="ph ph-link"></i>
                                    <a id="catalog-url-link" href="/catalogo/<?php echo htmlspecialchars($catalogSlug); ?>" target="_blank">
                                        stockify.com.ar/catalogo/<?php echo htmlspecialchars($catalogSlug); ?>
                                    </a>
                                    <button type="button" id="btn-copy-catalog-url" class="btn btn-secondary" style="margin:0; padding:6px 16px; font-size:0.75rem; height:auto;">
                                        <i class="ph ph-copy"></i> Copiar
                                    </button>
                                </div>
                            </div>

                            <!-- Botón para abrir el panel de personalización -->
                            <div style="margin-top: 1.5rem;">
                                <button type="button" id="btn-open-catalog-customizer" class="btn-catalog-customizer-trigger">
                                    <i class="ph ph-paint-brush"></i>
                                    <span>Personalizar Catálogo</span>
                                    <i class="ph ph-arrow-right" style="margin-left:auto;"></i>
                                </button>
                            </div>

                        </div><!-- /catalog-settings-body -->

                        <button type="submit" class="btn btn-primary" id="btn-guardar-catalogo" style="margin-top:1.5rem;">
                            <i class="ph ph-floppy-disk"></i> Guardar Configuración del Catálogo
                        </button>

                    </form>
                </div><!-- /catalogo-container -->
                <?php endif; ?>

                <div id="soporte-container" class="hidden">
                    <div style="text-align: center; padding: 2rem;">
                        <i class="ph ph-envelope-simple-open" style="font-size: 3rem; color: var(--accent-color);"></i>
                        <h3>Centro de Ayuda</h3>
                        <p style="color: #64748b; margin-bottom: 2rem;">¿Tenés algún problema? Estamos para ayudarte.
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem;">
                            <a href="mailto:soporte@stockify.com.ar" class="btn btn-primary" style="margin: 0; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; width: auto; min-width: 200px;"><i class="ph ph-envelope-simple"></i> Vía Email</a>
                            <a href="https://wa.me/5491163642040" target="_blank" class="btn" style="margin: 0; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; width: auto; min-width: 200px; background-color: var(--accent-green); color: #000; border: 2px solid #000;"><i class="ph ph-whatsapp-logo" style="font-size: 1.2rem;"></i> Vía WhatsApp</a>
                        </div>
                        <p style="color: #64748b90; margin-top: 2rem; font-size: 0.8rem;">Si deseas eliminar tu cuenta y
                            todos tus datos
                            por
                            completo, por favor, escríbenos.
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================================
             PANEL DE PERSONALIZACIÓN DEL CATÁLOGO (Full-screen overlay)
             ============================================================ -->
        <?php if ($canConfigRemito): ?>
        <div id="catalog-customizer-panel" class="catalog-customizer-overlay hidden">
            <div class="catalog-customizer-inner">

                <!-- HEADER DEL PANEL -->
                <div class="customizer-panel-header">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <i class="ph ph-paint-brush" style="font-size:1.4rem;"></i>
                        <h2 style="margin:0; font-size:1.2rem; font-weight:900;">Personalizar Catálogo</h2>
                    </div>
                    <button type="button" id="btn-close-catalog-customizer" class="customizer-close-btn">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                <!-- CUERPO DEL PANEL: izquierda = opciones, derecha = mockup -->
                <div class="customizer-panel-body">

                    <!-- ===== COLUMNA IZQUIERDA: OPCIONES ===== -->
                    <div class="customizer-controls">

                        <!-- Logo del Catálogo -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-image"></i> Logo del Negocio</h4>
                            <div class="rustic-block" style="display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                                    <div id="catalog-logo-preview-container" style="width: 80px; height: 80px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; background: #fafafa; border-radius: 50%; overflow: hidden; flex-shrink:0;">
                                        <img id="catalog-logo-preview" src="<?php echo htmlspecialchars($catalogSettings['logo_url'] ?? ''); ?>" alt="Vista previa" style="width: 100%; height: 100%; object-fit: cover; display: <?php echo !empty($catalogSettings['logo_url']) ? 'block' : 'none'; ?>;">
                                        <span id="catalog-logo-placeholder" style="color: #aaa; font-size: 0.7rem; text-align: center; padding: 4px; display: <?php echo empty($catalogSettings['logo_url']) ? 'block' : 'none'; ?>;">Sin logo</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <input type="file" id="catalog_logo_input" accept="image/*" style="display: none;">
                                        <input type="hidden" id="catalog_logo_url" value="<?php echo htmlspecialchars($catalogSettings['logo_url'] ?? ''); ?>">
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('catalog_logo_input').click();" style="margin:0; width: auto; font-size: 0.8rem; padding: 6px 14px;">Seleccionar Imagen</button>
                                        <button type="button" class="btn btn-secondary" id="btn-delete-catalog-logo" style="margin:0; width: auto; font-size: 0.8rem; padding: 6px 14px; color: var(--accent-red); border-color: var(--accent-red); display: <?php echo !empty($catalogSettings['logo_url']) ? 'block' : 'none'; ?>;">Eliminar</button>
                                    </div>
                                </div>
                                <p style="font-size: 0.72rem; color: #666; margin: 0;">PNG, JPG, WEBP. Si no subís imagen, se mostrará la inicial del negocio.</p>
                            </div>
                        </div>

                        <!-- Información de Contacto -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-address-book"></i> Contacto Público</h4>
                            <div class="config-grid" style="grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_whatsapp"><i class="ph ph-whatsapp-logo"></i> WhatsApp</label>
                                    <input class="config-input" type="text" id="catalog_whatsapp"
                                        value="<?php echo htmlspecialchars($catalogSettings['whatsapp'] ?? ''); ?>"
                                        placeholder="5491123456789" style="margin-bottom:0;">
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_instagram"><i class="ph ph-instagram-logo"></i> Instagram</label>
                                    <input class="config-input" type="text" id="catalog_instagram"
                                        value="<?php echo htmlspecialchars($catalogSettings['instagram'] ?? ''); ?>"
                                        placeholder="@minegocio" style="margin-bottom:0;">
                                </div>
                                <div class="rustic-block" style="grid-column: 1 / -1;">
                                    <label class="option-label" for="catalog_address"><i class="ph ph-map-pin"></i> Dirección</label>
                                    <input class="config-input" type="text" id="catalog_address"
                                        value="<?php echo htmlspecialchars($catalogSettings['address'] ?? ''); ?>"
                                        placeholder="Av. Corrientes 1234, CABA" style="margin-bottom:0;">
                                </div>
                            </div>
                        </div>

                        <!-- Opciones de Visualización -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-eye"></i> Visualización</h4>
                            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Mostrar precio</label>
                                        <p>Precio visible en cada producto.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_show_price">
                                        <input type="checkbox" id="catalog_show_price" <?php echo ($catalogSettings['show_price'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Mostrar stock exacto</label>
                                        <p>Si no, muestra "Disponible" o "Sin Stock".</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_show_stock">
                                        <input type="checkbox" id="catalog_show_stock" <?php echo ($catalogSettings['show_exact_stock'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Apariencia -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-palette"></i> Apariencia</h4>
                            <div class="config-grid" style="grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_theme_color">Color Principal</label>
                                    <select class="config-input" id="catalog_theme_color" style="margin-bottom:0;">
                                        <option value="accent-color" <?php echo ($catalogSettings['theme_color'] ?? 'accent-color') === 'accent-color' ? 'selected' : ''; ?>>Aleatorio StockiFy</option>
                                        <option value="accent-green" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-green' ? 'selected' : ''; ?>>Verde</option>
                                        <option value="accent-blue" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-blue' ? 'selected' : ''; ?>>Azul</option>
                                        <option value="accent-red" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-red' ? 'selected' : ''; ?>>Rojo</option>
                                        <option value="accent-yellow" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-yellow' ? 'selected' : ''; ?>>Amarillo</option>
                                        <option value="accent-violet" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-violet' ? 'selected' : ''; ?>>Violeta</option>
                                    </select>
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_theme_pattern">Patrón de Fondo</label>
                                    <select class="config-input" id="catalog_theme_pattern" style="margin-bottom:0;">
                                        <option value="dots" <?php echo ($catalogSettings['theme_pattern'] ?? 'dots') === 'dots' ? 'selected' : ''; ?>>Puntos</option>
                                        <option value="grid" <?php echo ($catalogSettings['theme_pattern'] ?? '') === 'grid' ? 'selected' : ''; ?>>Cuadrícula</option>
                                        <option value="lines" <?php echo ($catalogSettings['theme_pattern'] ?? '') === 'lines' ? 'selected' : ''; ?>>Líneas</option>
                                        <option value="none" <?php echo ($catalogSettings['theme_pattern'] ?? '') === 'none' ? 'selected' : ''; ?>>Liso</option>
                                    </select>
                                </div>
                                <div class="rustic-block" style="grid-column: 1 / -1; margin-top: 0.5rem;">
                                    <label class="option-label" for="catalog_font_family"><i class="ph ph-text-t"></i> Tipografía General</label>
                                    <select class="config-input" id="catalog_font_family" style="margin-bottom:0;">
                                        <option value="Outfit" <?php echo ($catalogSettings['font_family'] ?? 'Outfit') === 'Outfit' ? 'selected' : ''; ?>>Outfit (Moderna, geométrica)</option>
                                        <option value="Inter" <?php echo ($catalogSettings['font_family'] ?? '') === 'Inter' ? 'selected' : ''; ?>>Inter (Limpia, corporativa)</option>
                                        <option value="Lexend" <?php echo ($catalogSettings['font_family'] ?? '') === 'Lexend' ? 'selected' : ''; ?>>Lexend (Altamente legible)</option>
                                        <option value="Space Grotesk" <?php echo ($catalogSettings['font_family'] ?? '') === 'Space Grotesk' ? 'selected' : ''; ?>>Space Grotesk (Tech, neobrutalista)</option>
                                        <option value="Syne" <?php echo ($catalogSettings['font_family'] ?? '') === 'Syne' ? 'selected' : ''; ?>>Syne (Artística, expresiva)</option>
                                        <option value="Poppins" <?php echo ($catalogSettings['font_family'] ?? '') === 'Poppins' ? 'selected' : ''; ?>>Poppins (Redondeada, amigable)</option>
                                        <option value="Montserrat" <?php echo ($catalogSettings['font_family'] ?? '') === 'Montserrat' ? 'selected' : ''; ?>>Montserrat (Llamativa, clásica)</option>
                                        <option value="Playfair Display" <?php echo ($catalogSettings['font_family'] ?? '') === 'Playfair Display' ? 'selected' : ''; ?>>Playfair Display (Elegante, luxury)</option>
                                        <option value="Courier Prime" <?php echo ($catalogSettings['font_family'] ?? '') === 'Courier Prime' ? 'selected' : ''; ?>>Courier Prime (Monospace, retro)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Acción -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-cursor-click"></i> Botón de Acción</h4>
                            <div class="config-grid" style="grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_button_text">Texto</label>
                                    <input class="config-input" type="text" id="catalog_button_text"
                                        value="<?php echo htmlspecialchars($catalogSettings['button_text'] ?? 'Consultar'); ?>"
                                        placeholder="Consultar" style="margin-bottom:0;">
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_button_icon">Ícono</label>
                                    <select class="config-input" id="catalog_button_icon" style="margin-bottom:0;">
                                        <option value="ph-whatsapp-logo" <?php echo ($catalogSettings['button_icon'] ?? 'ph-whatsapp-logo') === 'ph-whatsapp-logo' ? 'selected' : ''; ?>>WhatsApp</option>
                                        <option value="ph-instagram-logo" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-instagram-logo' ? 'selected' : ''; ?>>Instagram</option>
                                        <option value="ph-link" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-link' ? 'selected' : ''; ?>>Enlace</option>
                                        <option value="ph-envelope" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-envelope' ? 'selected' : ''; ?>>Mail</option>
                                        <option value="ph-shopping-cart" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-shopping-cart' ? 'selected' : ''; ?>>Carrito</option>
                                    </select>
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_button_color">Color</label>
                                    <select class="config-input" id="catalog_button_color" style="margin-bottom:0;">
                                        <option value="whatsapp-green" <?php echo ($catalogSettings['button_color'] ?? 'whatsapp-green') === 'whatsapp-green' ? 'selected' : ''; ?>>Verde WhatsApp</option>
                                        <option value="instagram-pink" <?php echo ($catalogSettings['button_color'] ?? '') === 'instagram-pink' ? 'selected' : ''; ?>>Rosa Instagram</option>
                                        <option value="facebook-blue" <?php echo ($catalogSettings['button_color'] ?? '') === 'facebook-blue' ? 'selected' : ''; ?>>Azul Facebook</option>
                                        <option value="accent-green" <?php echo ($catalogSettings['button_color'] ?? '') === 'accent-green' ? 'selected' : ''; ?>>Verde StockiFy</option>
                                        <option value="accent-red" <?php echo ($catalogSettings['button_color'] ?? '') === 'accent-red' ? 'selected' : ''; ?>>Rojo StockiFy</option>
                                        <option value="accent-blue" <?php echo ($catalogSettings['button_color'] ?? '') === 'accent-blue' ? 'selected' : ''; ?>>Azul StockiFy</option>
                                        <option value="accent-yellow" <?php echo ($catalogSettings['button_color'] ?? '') === 'accent-yellow' ? 'selected' : ''; ?>>Amarillo StockiFy</option>
                                        <option value="accent-violet" <?php echo ($catalogSettings['button_color'] ?? '') === 'accent-violet' ? 'selected' : ''; ?>>Violeta StockiFy</option>
                                        <option value="accent-color" <?php echo ($catalogSettings['button_color'] ?? '') === 'accent-color' ? 'selected' : ''; ?>>Aleatorio StockiFy</option>
                                        <option value="color-black" <?php echo ($catalogSettings['button_color'] ?? '') === 'color-black' ? 'selected' : ''; ?>>Negro</option>
                                    </select>
                                </div>
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Habilitar botón</label>
                                        <p>Mostrar en cada producto.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_show_action_button">
                                        <input type="checkbox" id="catalog_show_action_button" <?php echo ($catalogSettings['show_action_button'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="rustic-block" style="grid-column: 1 / -1;">
                                    <label class="option-label" for="catalog_button_link">Enlace personalizado (opcional)</label>
                                    <input class="config-input" type="text" id="catalog_button_link"
                                        value="<?php echo htmlspecialchars($catalogSettings['button_link'] ?? ''); ?>"
                                        placeholder="https://wa.me/... (dejá vacío para el default)" style="margin-bottom:0;">
                                </div>
                            </div>
                        </div>

                        <!-- Colorimetría -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-drop"></i> Colorimetría</h4>
                            <div style="display:flex; flex-direction:column; gap:0.6rem;">

                                <!-- Fila 1: Fondo + Patrón -->
                                <div class="config-grid" style="grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_bg">Fondo de página</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_bg" style="margin-bottom:0; flex:1;">
                                                <option value="#F4F4F6" <?php echo ($catalogSettings['color_bg'] ?? '#F4F4F6') === '#F4F4F6' ? 'selected' : ''; ?>>Gris claro (default)</option>
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_bg'] ?? '') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco</option>
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_bg'] ?? '') === '#1A1A1A' ? 'selected' : ''; ?>>Negro</option>
                                                <option value="#EDF2F7" <?php echo ($catalogSettings['color_bg'] ?? '') === '#EDF2F7' ? 'selected' : ''; ?>>Azul hielo</option>
                                                <option value="#FFF9F0" <?php echo ($catalogSettings['color_bg'] ?? '') === '#FFF9F0' ? 'selected' : ''; ?>>Crema</option>
                                                <option value="custom" <?php $cbg = $catalogSettings['color_bg'] ?? '#F4F4F6'; echo !in_array($cbg, ['#F4F4F6','#FFFFFF','#1A1A1A','#EDF2F7','#FFF9F0']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_bg_custom" value="<?php echo htmlspecialchars($catalogSettings['color_bg'] ?? '#F4F4F6'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_pattern">Color del patrón</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_pattern" style="margin-bottom:0; flex:1;">
                                                <option value="rgba(0,0,0,0.08)" <?php echo ($catalogSettings['color_pattern'] ?? 'rgba(0,0,0,0.08)') === 'rgba(0,0,0,0.08)' ? 'selected' : ''; ?>>Negro suave (default)</option>
                                                <option value="rgba(0,0,0,0.2)" <?php echo ($catalogSettings['color_pattern'] ?? '') === 'rgba(0,0,0,0.2)' ? 'selected' : ''; ?>>Negro intenso</option>
                                                <option value="rgba(255,255,255,0.3)" <?php echo ($catalogSettings['color_pattern'] ?? '') === 'rgba(255,255,255,0.3)' ? 'selected' : ''; ?>>Blanco</option>
                                                <option value="rgba(163,190,140,0.4)" <?php echo ($catalogSettings['color_pattern'] ?? '') === 'rgba(163,190,140,0.4)' ? 'selected' : ''; ?>>Verde</option>
                                                <option value="rgba(136,192,208,0.4)" <?php echo ($catalogSettings['color_pattern'] ?? '') === 'rgba(136,192,208,0.4)' ? 'selected' : ''; ?>>Azul</option>
                                                <option value="rgba(235,203,139,0.4)" <?php echo ($catalogSettings['color_pattern'] ?? '') === 'rgba(235,203,139,0.4)' ? 'selected' : ''; ?>>Amarillo</option>
                                                <option value="custom" <?php $cpat = $catalogSettings['color_pattern'] ?? 'rgba(0,0,0,0.08)'; echo !in_array($cpat, ['rgba(0,0,0,0.08)','rgba(0,0,0,0.2)','rgba(255,255,255,0.3)','rgba(163,190,140,0.4)','rgba(136,192,208,0.4)','rgba(235,203,139,0.4)']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_pattern_custom" value="<?php $cpat = $catalogSettings['color_pattern'] ?? 'rgba(0,0,0,0.08)'; echo str_starts_with($cpat, '#') ? htmlspecialchars($cpat) : '#8A8A8A'; ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 2: Tarjetas + Acento -->
                                <div class="config-grid" style="grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_card">Color tarjetas</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_card" style="margin-bottom:0; flex:1;">
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_card'] ?? '#FFFFFF') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco (default)</option>
                                                <option value="#F4F4F6" <?php echo ($catalogSettings['color_card'] ?? '') === '#F4F4F6' ? 'selected' : ''; ?>>Gris claro</option>
                                                <option value="#FFF9F0" <?php echo ($catalogSettings['color_card'] ?? '') === '#FFF9F0' ? 'selected' : ''; ?>>Crema</option>
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_card'] ?? '') === '#1A1A1A' ? 'selected' : ''; ?>>Negro</option>
                                                <option value="custom" <?php $ccard = $catalogSettings['color_card'] ?? '#FFFFFF'; echo !in_array($ccard, ['#FFFFFF','#F4F4F6','#FFF9F0','#1A1A1A']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_card_custom" value="<?php echo htmlspecialchars($catalogSettings['color_card'] ?? '#FFFFFF'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_accent">Acento (pills, logo)</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_accent" style="margin-bottom:0; flex:1;">
                                                <option value="theme" <?php echo ($catalogSettings['color_accent'] ?? 'theme') === 'theme' ? 'selected' : ''; ?>>Desde color principal</option>
                                                <option value="#A3BE8C" <?php echo ($catalogSettings['color_accent'] ?? '') === '#A3BE8C' ? 'selected' : ''; ?>>Verde StockiFy</option>
                                                <option value="#88C0D0" <?php echo ($catalogSettings['color_accent'] ?? '') === '#88C0D0' ? 'selected' : ''; ?>>Azul StockiFy</option>
                                                <option value="#EBCB8B" <?php echo ($catalogSettings['color_accent'] ?? '') === '#EBCB8B' ? 'selected' : ''; ?>>Amarillo StockiFy</option>
                                                <option value="#BF616A" <?php echo ($catalogSettings['color_accent'] ?? '') === '#BF616A' ? 'selected' : ''; ?>>Rojo StockiFy</option>
                                                <option value="#B48EAD" <?php echo ($catalogSettings['color_accent'] ?? '') === '#B48EAD' ? 'selected' : ''; ?>>Violeta StockiFy</option>
                                                <option value="#25D366" <?php echo ($catalogSettings['color_accent'] ?? '') === '#25D366' ? 'selected' : ''; ?>>Verde WhatsApp</option>
                                                <option value="custom" <?php $cacc = $catalogSettings['color_accent'] ?? 'theme'; echo !in_array($cacc, ['theme','#A3BE8C','#88C0D0','#EBCB8B','#BF616A','#B48EAD','#25D366']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_accent_custom" value="<?php echo htmlspecialchars(($catalogSettings['color_accent'] ?? 'theme') === 'theme' ? '#88C0D0' : ($catalogSettings['color_accent'] ?? '#88C0D0')); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 3: Textos -->
                                <div class="config-grid" style="grid-template-columns:1fr 1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_label">Etiqueta cat.</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_label" style="margin-bottom:0; flex:1;">
                                                <option value="#8A8A8A" <?php echo ($catalogSettings['color_label'] ?? '#8A8A8A') === '#8A8A8A' ? 'selected' : ''; ?>>Gris (default)</option>
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_label'] ?? '') === '#1A1A1A' ? 'selected' : ''; ?>>Negro</option>
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_label'] ?? '') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco</option>
                                                <option value="custom" <?php $clab = $catalogSettings['color_label'] ?? '#8A8A8A'; echo !in_array($clab, ['#8A8A8A','#1A1A1A','#FFFFFF']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_label_custom" value="<?php echo htmlspecialchars($catalogSettings['color_label'] ?? '#8A8A8A'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_title">Título</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_title" style="margin-bottom:0; flex:1;">
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_title'] ?? '#1A1A1A') === '#1A1A1A' ? 'selected' : ''; ?>>Negro (default)</option>
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_title'] ?? '') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco</option>
                                                <option value="#8A8A8A" <?php echo ($catalogSettings['color_title'] ?? '') === '#8A8A8A' ? 'selected' : ''; ?>>Gris</option>
                                                <option value="custom" <?php $ctit = $catalogSettings['color_title'] ?? '#1A1A1A'; echo !in_array($ctit, ['#1A1A1A','#FFFFFF','#8A8A8A']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_title_custom" value="<?php echo htmlspecialchars($catalogSettings['color_title'] ?? '#1A1A1A'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_price">Precio</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_price" style="margin-bottom:0; flex:1;">
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_price'] ?? '#1A1A1A') === '#1A1A1A' ? 'selected' : ''; ?>>Negro (default)</option>
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_price'] ?? '') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco</option>
                                                <option value="#A3BE8C" <?php echo ($catalogSettings['color_price'] ?? '') === '#A3BE8C' ? 'selected' : ''; ?>>Verde</option>
                                                <option value="#BF616A" <?php echo ($catalogSettings['color_price'] ?? '') === '#BF616A' ? 'selected' : ''; ?>>Rojo</option>
                                                <option value="custom" <?php $cpri = $catalogSettings['color_price'] ?? '#1A1A1A'; echo !in_array($cpri, ['#1A1A1A','#FFFFFF','#A3BE8C','#BF616A']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_price_custom" value="<?php echo htmlspecialchars($catalogSettings['color_price'] ?? '#1A1A1A'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 4: Encabezado + Botones contacto + Fondo etiquetas -->
                                <div class="config-grid" style="grid-template-columns:1fr 1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_header_bg">Fondo del head</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_header_bg" style="margin-bottom:0; flex:1;">
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_header_bg'] ?? '#FFFFFF') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco (default)</option>
                                                <option value="#F4F4F6" <?php echo ($catalogSettings['color_header_bg'] ?? '') === '#F4F4F6' ? 'selected' : ''; ?>>Gris claro</option>
                                                <option value="#FFF9F0" <?php echo ($catalogSettings['color_header_bg'] ?? '') === '#FFF9F0' ? 'selected' : ''; ?>>Crema</option>
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_header_bg'] ?? '') === '#1A1A1A' ? 'selected' : ''; ?>>Negro</option>
                                                <option value="custom" <?php $chbg = $catalogSettings['color_header_bg'] ?? '#FFFFFF'; echo !in_array($chbg, ['#FFFFFF','#F4F4F6','#FFF9F0','#1A1A1A']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_header_bg_custom" value="<?php echo htmlspecialchars($catalogSettings['color_header_bg'] ?? '#FFFFFF'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_social_bg">Botones contacto</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_social_bg" style="margin-bottom:0; flex:1;">
                                                <option value="#FFFFFF" <?php echo ($catalogSettings['color_social_bg'] ?? '#FFFFFF') === '#FFFFFF' ? 'selected' : ''; ?>>Blanco (default)</option>
                                                <option value="#F4F4F6" <?php echo ($catalogSettings['color_social_bg'] ?? '') === '#F4F4F6' ? 'selected' : ''; ?>>Gris claro</option>
                                                <option value="#FFF9F0" <?php echo ($catalogSettings['color_social_bg'] ?? '') === '#FFF9F0' ? 'selected' : ''; ?>>Crema</option>
                                                <option value="#1A1A1A" <?php echo ($catalogSettings['color_social_bg'] ?? '') === '#1A1A1A' ? 'selected' : ''; ?>>Negro</option>
                                                <option value="custom" <?php $csbg = $catalogSettings['color_social_bg'] ?? '#FFFFFF'; echo !in_array($csbg, ['#FFFFFF','#F4F4F6','#FFF9F0','#1A1A1A']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_social_bg_custom" value="<?php echo htmlspecialchars($catalogSettings['color_social_bg'] ?? '#FFFFFF'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                    <div class="rustic-block">
                                        <label class="option-label" for="catalog_color_badge_bg">Fondo etiquetas</label>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <select class="config-input" id="catalog_color_badge_bg" style="margin-bottom:0; flex:1;">
                                                <option value="#A3BE8C" <?php echo ($catalogSettings['color_badge_bg'] ?? '#A3BE8C') === '#A3BE8C' ? 'selected' : ''; ?>>Verde (default)</option>
                                                <option value="#BF616A" <?php echo ($catalogSettings['color_badge_bg'] ?? '') === '#BF616A' ? 'selected' : ''; ?>>Rojo</option>
                                                <option value="#88C0D0" <?php echo ($catalogSettings['color_badge_bg'] ?? '') === '#88C0D0' ? 'selected' : ''; ?>>Azul</option>
                                                <option value="#EBCB8B" <?php echo ($catalogSettings['color_badge_bg'] ?? '') === '#EBCB8B' ? 'selected' : ''; ?>>Amarillo</option>
                                                <option value="#B48EAD" <?php echo ($catalogSettings['color_badge_bg'] ?? '') === '#B48EAD' ? 'selected' : ''; ?>>Violeta</option>
                                                <option value="custom" <?php $cbbg = $catalogSettings['color_badge_bg'] ?? '#A3BE8C'; echo !in_array($cbbg, ['#A3BE8C','#BF616A','#88C0D0','#EBCB8B','#B48EAD']) ? 'selected' : ''; ?>>Personalizado...</option>
                                            </select>
                                            <input type="color" id="catalog_color_badge_bg_custom" value="<?php echo htmlspecialchars($catalogSettings['color_badge_bg'] ?? '#A3BE8C'); ?>" class="custom-color-picker">
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Sombras -->
                        <div class="customizer-section">
                            <h4 class="customizer-section-title"><i class="ph ph-intersect"></i> Sombras</h4>
                            <div class="config-grid" style="grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0;">
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Filtros</label>
                                        <p>Buscador y categorías.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_shadow_filter_section">
                                        <input type="checkbox" id="catalog_shadow_filter_section" <?php echo ($catalogSettings['shadow_filter_section'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Pills Cat.</label>
                                        <p>Botones de categorías.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_shadow_category_pill">
                                        <input type="checkbox" id="catalog_shadow_category_pill" <?php echo ($catalogSettings['shadow_category_pill'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Productos</label>
                                        <p>Tarjetas de productos.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_shadow_product_card">
                                        <input type="checkbox" id="catalog_shadow_product_card" <?php echo ($catalogSettings['shadow_product_card'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="catalog-row-block">
                                    <div>
                                        <label class="option-label">Detalle</label>
                                        <p>Diálogo de detalles.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_shadow_modal">
                                        <input type="checkbox" id="catalog_shadow_modal" <?php echo ($catalogSettings['shadow_modal'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div><!-- /customizer-controls -->

                    <!-- ===== COLUMNA DERECHA: MOCKUP / PREVIEW =====-->
                    <div class="customizer-preview">
                        <div class="customizer-preview-label">
                            <i class="ph ph-device-mobile"></i> Vista previa del catálogo
                        </div>

                        <!-- Controles de zoom -->
                        <div class="mockup-zoom-controls">
                            <button type="button" class="mockup-zoom-btn" id="mockup-zoom-out" title="Alejar"><i class="ph ph-minus"></i></button>
                            <span class="mockup-zoom-label" id="mockup-zoom-label">100%</span>
                            <button type="button" class="mockup-zoom-btn" id="mockup-zoom-in" title="Acercar"><i class="ph ph-plus"></i></button>
                        </div>

                        <!-- Mockup del catálogo -->
                        <div class="mockup-zoom-wrapper">
                        <div class="catalog-mockup" id="catalog-mockup">

                            <!-- Nav falso del catálogo -->
                            <div class="mockup-nav" id="mockup-nav">
                                <div class="mockup-nav-logo" id="mockup-nav-logo">
                                    <div class="mockup-logo-circle" id="mockup-logo-circle"><?php echo htmlspecialchars(!empty($inventoryName) ? mb_strtoupper(mb_substr($inventoryName, 0, 1)) : 'M'); ?></div>
                                    <span class="mockup-business-name" id="mockup-business-name" data-original-name="<?php echo htmlspecialchars(!empty($inventoryName) ? $inventoryName : 'Mi Negocio'); ?>"><?php echo htmlspecialchars(!empty($inventoryName) ? $inventoryName : 'Mi Negocio'); ?></span>
                                </div>
                                <div class="mockup-nav-contacts" id="mockup-nav-contacts">
                                    <div class="mockup-contact-btn mockup-map" id="mockup-contact-map" style="display:none;"><i class="ph ph-map-pin"></i></div>
                                    <div class="mockup-contact-btn mockup-ig" id="mockup-contact-ig" style="display:none;"><i class="ph ph-instagram-logo"></i></div>
                                    <div class="mockup-contact-btn mockup-wa" id="mockup-contact-wa" style="display:none;"><i class="ph ph-whatsapp-logo"></i></div>
                                </div>
                            </div>

                            <!-- Fondo con patrón -->
                            <div class="mockup-bg" id="mockup-bg">

                                <!-- Buscador y Filtros falsos -->
                                <div class="mockup-filter-section">
                                    <div class="mockup-search">
                                        <i class="ph ph-magnifying-glass"></i>
                                        <span>Buscar productos por nombre...</span>
                                    </div>
                                    <div class="mockup-categories">
                                        <span class="mockup-category-pill active">Todos</span>
                                        <span class="mockup-category-pill">General</span>
                                        <span class="mockup-category-pill">Muebles</span>
                                    </div>
                                </div>

                                <!-- Una sola tarjeta de producto -->
                                <div class="mockup-products-grid mockup-single">
                                    <div class="mockup-product-card">
                                        <div class="mockup-product-image-container">
                                            <span class="mockup-product-badge badge-stock">Disponible</span>
                                            <img
                                                src="https://http2.mlstatic.com/D_Q_NP_2X_761181-MLA93322598447_092025-T.webp"
                                                alt="Banqueta Alta Vittoria"
                                                class="mockup-product-img-real"
                                                loading="lazy"
                                            >
                                        </div>
                                        <div class="mockup-product-details">
                                            <span class="mockup-product-category">General</span>
                                            <h3 class="mockup-product-title">Banqueta Alta Vittoria Premium Negro</h3>
                                            <div class="mockup-product-footer">
                                                <span class="mockup-product-price">$74.700</span>
                                                <button class="mockup-action-btn">
                                                    <i class="ph ph-whatsapp-logo mockup-btn-icon"></i>
                                                    <span class="mockup-btn-text">Escribir</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /mockup-bg -->
                        </div><!-- /catalog-mockup -->
                        </div><!-- /mockup-zoom-wrapper -->
                    </div><!-- /customizer-preview -->

                </div><!-- /customizer-panel-body -->

            </div><!-- /catalog-customizer-inner -->
        </div><!-- /catalog-customizer-panel -->
        <?php endif; ?>

        <div class="view-container flex-column justify-left align-center hidden" id="modif-form-container"
            style="z-index: 1001;">
            <p id="return-btn" class="return-btn" style="cursor:pointer; align-self: flex-end;">&times;</p>

            <form style="margin-top: 1rem; width: 100%;" id="email-form" class="hidden">
                <h3 style="margin-bottom: 1rem;">Nuevo E-Mail</h3>
                <input class="config-input" type="email" id="new-email" name='new-email' placeholder="nuevo@ejemplo.com"
                    required>
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Enviar
                    Código</button>
            </form>

            <form style="margin-top: 1rem; width: 100%;" id="code-form" class="hidden">
                <h3 style="margin-bottom: 1rem;">Código de Verificación</h3>
                <p style="font-size: 0.8rem; color: #666; margin-bottom: 1rem;">Enviamos un código de 6 dígitos a tu
                    nuevo correo.</p>
                <input class="config-input" type="text" name="code" inputmode="numeric" maxlength="6"
                    placeholder="123456" required style="text-align: center; letter-spacing: 5px; font-size: 1.2rem;">
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Verificar y
                    Cambiar</button>
            </form>

            <div class="hidden" id="save-email-container" style="text-align: center;">
                <i class="ph ph-check-circle" style="font-size: 3rem; color: var(--accent-green);"></i>
                <h3 style="margin-top: 1rem">¡Email Verificado!</h3>
                <p>Tu nuevo email será: <br><strong id="new-email-text" style="color:var(--accent-color)"></strong></p>
                <button style="margin-top: 2rem; width: 100%;" class="btn btn-primary" id="save-email-btn">Confirmar
                    Cambio</button>
            </div>


        </div>
    </main>

    <script>
        window.userData = {
            username: "<?php echo addslashes($currentUser['username'] ?? ''); ?>",
            full_name: "<?php echo addslashes($currentUser['full_name'] ?? ''); ?>",
            dni: "<?php echo addslashes($currentUser['dni'] ?? ''); ?>",
            cell: "<?php echo addslashes($currentUser['cell'] ?? ''); ?>"
        };
        <?php if ($canConfigRemito): ?>
        window.catalogData = {
            inventory_id: <?php echo (int)$activeInventoryId; ?>,
            catalog_active: <?php echo $catalogActive ? 'true' : 'false'; ?>,
            catalog_slug: "<?php echo addslashes($catalogSlug); ?>",
            whatsapp: "<?php echo addslashes($catalogSettings['whatsapp'] ?? ''); ?>",
            instagram: "<?php echo addslashes($catalogSettings['instagram'] ?? ''); ?>",
            address: "<?php echo addslashes($catalogSettings['address'] ?? ''); ?>",
            show_price: <?php echo ($catalogSettings['show_price'] ?? true) ? 'true' : 'false'; ?>,
            show_exact_stock: <?php echo ($catalogSettings['show_exact_stock'] ?? true) ? 'true' : 'false'; ?>,
            show_action_button: <?php echo ($catalogSettings['show_action_button'] ?? true) ? 'true' : 'false'; ?>,
            logo_url: "<?php echo addslashes($catalogSettings['logo_url'] ?? ''); ?>",
            button_text: "<?php echo addslashes($catalogSettings['button_text'] ?? 'Consultar'); ?>",
            button_link: "<?php echo addslashes($catalogSettings['button_link'] ?? ''); ?>",
            button_icon: "<?php echo addslashes($catalogSettings['button_icon'] ?? 'ph-whatsapp-logo'); ?>",
            button_color: "<?php echo addslashes($catalogSettings['button_color'] ?? 'whatsapp-green'); ?>",
            theme_color: "<?php echo addslashes($catalogSettings['theme_color'] ?? 'accent-color'); ?>",
            theme_pattern: "<?php echo addslashes($catalogSettings['theme_pattern'] ?? 'dots'); ?>"
        };
        window.inventoryData = {
            remito_logo: "<?php echo addslashes($remitoLogo); ?>",
            remito_description: "<?php echo addslashes($remitoDescription); ?>",
            remito_url: "<?php echo addslashes($remitoUrl); ?>"
        };
        <?php endif; ?>
    </script>
</body>

</html>