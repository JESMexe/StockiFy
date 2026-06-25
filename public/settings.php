<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | StockiFy</title>

    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/configuration.css">
    <link rel="stylesheet" href="assets/css/sweetalert.css">

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="assets/js/sweetalert2.all.min.js?v=11.0"></script>
    <script>
        if (typeof Swal === 'undefined') {
            console.warn("SweetAlert2 local no pudo cargarse. Cargando fallback desde CDN...");
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }
    </script>
    <script src="assets/js/theme.js"></script>
    <script type="module" src="./assets/js/configuration.js"></script>
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

// Catalog data
$catalogActive = 0;
$catalogSlug = '';
$catalogSettings = [];

if ($canConfigRemito) {
    $dbInstance = \App\core\Database::getInstance();
    $stmtInv = $dbInstance->prepare("SELECT remito_logo_path, remito_description, remito_url, catalog_active, catalog_slug, catalog_settings FROM inventories WHERE id = ?");
    $stmtInv->execute([$activeInventoryId]);
    $invSettings = $stmtInv->fetch(PDO::FETCH_ASSOC);
    if ($invSettings) {
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
                        <div class="rustic-block" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                            <div>
                                <label class="option-label" style="margin:0;">Activar Catálogo Público</label>
                                <p style="font-size:0.8rem; color:#64748b; margin:4px 0 0;">Cuando está activo, cualquier persona con el link puede ver tus productos publicados.</p>
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
                                <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                    <span style="font-size:0.85rem; color:#64748b; white-space:nowrap;">stockify.com.ar/catalogo/</span>
                                    <div style="flex:1; position:relative;">
                                        <input class="config-input" type="text" id="catalog_slug"
                                            value="<?php echo htmlspecialchars($catalogSlug); ?>"
                                            placeholder="mi-negocio"
                                            autocomplete="off"
                                            pattern="[a-z0-9\-]+"
                                            style="padding-right:2.5rem;">
                                        <span id="slug-status-icon" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:1.1rem;"></span>
                                    </div>
                                </div>
                                <p id="slug-feedback" style="font-size:0.8rem; margin-top:4px; min-height:1em;"></p>
                                <div id="catalog-url-preview" style="display:<?php echo $catalogSlug ? 'flex' : 'none'; ?>; align-items:center; gap:0.5rem; margin-top:0.75rem; padding:0.6rem 1rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; flex-wrap:wrap;">
                                    <i class="ph ph-link" style="color:#16a34a;"></i>
                                    <a id="catalog-url-link" href="/catalogo/<?php echo htmlspecialchars($catalogSlug); ?>" target="_blank"
                                        style="color:#16a34a; font-size:0.85rem; text-decoration:none; word-break:break-all;">
                                        stockify.com.ar/catalogo/<?php echo htmlspecialchars($catalogSlug); ?>
                                    </a>
                                    <button type="button" id="btn-copy-catalog-url" class="btn btn-secondary" style="margin:0; padding:4px 12px; font-size:0.75rem; height:auto;">
                                        <i class="ph ph-copy"></i> Copiar
                                    </button>
                                </div>
                            </div>

                            <!-- Logo del Catálogo -->
                            <div class="rustic-block" style="display: flex; flex-direction: column; gap: 10px;">
                                <label class="option-label">Logo del Catálogo <span class="helper-tag">Imagen opcional</span></label>
                                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                    <div id="catalog-logo-preview-container" style="width: 100px; height: 100px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; background: #fafafa; border-radius: 50%; overflow: hidden; position: relative;">
                                        <img id="catalog-logo-preview" src="<?php echo htmlspecialchars($catalogSettings['logo_url'] ?? ''); ?>" alt="Vista previa" style="width: 100%; height: 100%; object-fit: cover; display: <?php echo !empty($catalogSettings['logo_url']) ? 'block' : 'none'; ?>;">
                                        <span id="catalog-logo-placeholder" style="color: #aaa; font-size: 0.8rem; text-align: center; padding: 5px; display: <?php echo empty($catalogSettings['logo_url']) ? 'block' : 'none'; ?>;">Sin Imagen</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <input type="file" id="catalog_logo_input" accept="image/*" style="display: none;">
                                        <input type="hidden" id="catalog_logo_url" value="<?php echo htmlspecialchars($catalogSettings['logo_url'] ?? ''); ?>">
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('catalog_logo_input').click();" style="margin:0; width: auto; font-size: 0.85rem; padding: 8px 16px;">Seleccionar Imagen</button>
                                        <button type="button" class="btn btn-secondary" id="btn-delete-catalog-logo" style="margin:0; width: auto; font-size: 0.85rem; padding: 8px 16px; color: var(--accent-red); border-color: var(--accent-red); display: <?php echo !empty($catalogSettings['logo_url']) ? 'block' : 'none'; ?>;">Eliminar Imagen</button>
                                    </div>
                                </div>
                                <p style="font-size: 0.75rem; color: #666; margin: 0;">Si no subes una imagen, se mostrará la inicial del inventario. Formatos recomendados: PNG, JPG, JPEG, WEBP.</p>
                            </div>

                            <!-- Información de Contacto -->
                            <h3 class="config-section-title" style="margin-top:2rem;">Información de Contacto Pública</h3>
                            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1rem;">Estos datos aparecerán en tu catálogo público para que los clientes puedan contactarte.</p>

                            <div class="config-grid">
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_whatsapp"><i class="ph ph-whatsapp-logo"></i> WhatsApp</label>
                                    <input class="config-input" type="text" id="catalog_whatsapp"
                                        value="<?php echo htmlspecialchars($catalogSettings['whatsapp'] ?? ''); ?>"
                                        placeholder="5491123456789 (con código de país)">
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_instagram"><i class="ph ph-instagram-logo"></i> Instagram</label>
                                    <input class="config-input" type="text" id="catalog_instagram"
                                        value="<?php echo htmlspecialchars($catalogSettings['instagram'] ?? ''); ?>"
                                        placeholder="@minegocio">
                                </div>
                                <div class="rustic-block" style="grid-column: 1 / -1;">
                                    <label class="option-label" for="catalog_address"><i class="ph ph-map-pin"></i> Dirección Física</label>
                                    <input class="config-input" type="text" id="catalog_address"
                                        value="<?php echo htmlspecialchars($catalogSettings['address'] ?? ''); ?>"
                                        placeholder="Av. Corrientes 1234, CABA">
                                </div>
                            </div>

                            <!-- Opciones de visualización -->
                            <h3 class="config-section-title" style="margin-top:2rem;">Opciones de Visualización</h3>

                            <div class="config-grid">
                                <div class="rustic-block" style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                                    <div>
                                        <label class="option-label" style="margin:0;">Mostrar precio de venta</label>
                                        <p style="font-size:0.75rem; color:#64748b; margin:2px 0 0;">Los clientes verán el precio en cada producto.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_show_price">
                                        <input type="checkbox" id="catalog_show_price" <?php echo ($catalogSettings['show_price'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="rustic-block" style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                                    <div>
                                        <label class="option-label" style="margin:0;">Mostrar stock exacto</label>
                                        <p style="font-size:0.75rem; color:#64748b; margin:2px 0 0;">Si desactivás, muestra "Disponible" o "Sin Stock".</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_show_stock">
                                        <input type="checkbox" id="catalog_show_stock" <?php echo ($catalogSettings['show_exact_stock'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Apariencia General -->
                            <h3 class="config-section-title" style="margin-top:2rem;">Apariencia del Catálogo</h3>
                            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1rem;">Personalizá los colores y el fondo de tu catálogo web.</p>
                            
                            <div class="config-grid">
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_theme_color">Color Principal</label>
                                    <select class="config-input" id="catalog_theme_color">
                                        <option value="accent-color" <?php echo ($catalogSettings['theme_color'] ?? 'accent-color') === 'accent-color' ? 'selected' : ''; ?>>Aleatorio StockiFy</option>
                                        <option value="accent-green" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-green' ? 'selected' : ''; ?>>Verde StockiFy</option>
                                        <option value="accent-blue" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-blue' ? 'selected' : ''; ?>>Azul StockiFy</option>
                                        <option value="accent-red" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-red' ? 'selected' : ''; ?>>Rojo StockiFy</option>
                                        <option value="accent-yellow" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-yellow' ? 'selected' : ''; ?>>Amarillo StockiFy</option>
                                        <option value="accent-violet" <?php echo ($catalogSettings['theme_color'] ?? '') === 'accent-violet' ? 'selected' : ''; ?>>Violeta StockiFy</option>
                                    </select>
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_theme_pattern">Patrón de Fondo</label>
                                    <select class="config-input" id="catalog_theme_pattern">
                                        <option value="dots" <?php echo ($catalogSettings['theme_pattern'] ?? 'dots') === 'dots' ? 'selected' : ''; ?>>Puntos</option>
                                        <option value="grid" <?php echo ($catalogSettings['theme_pattern'] ?? '') === 'grid' ? 'selected' : ''; ?>>Cuadrícula</option>
                                        <option value="lines" <?php echo ($catalogSettings['theme_pattern'] ?? '') === 'lines' ? 'selected' : ''; ?>>Líneas</option>
                                        <option value="none" <?php echo ($catalogSettings['theme_pattern'] ?? '') === 'none' ? 'selected' : ''; ?>>Liso</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Opciones del Botón Consultar -->
                            <h3 class="config-section-title" style="margin-top:2rem;">Botón de Acción (Consultar)</h3>
                            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1rem;">Personalizá el botón que aparece en cada producto.</p>

                            <div class="config-grid">
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_button_text">Texto del Botón</label>
                                    <input class="config-input" type="text" id="catalog_button_text"
                                        value="<?php echo htmlspecialchars($catalogSettings['button_text'] ?? 'Consultar'); ?>"
                                        placeholder="Ej: Consultar por WhatsApp">
                                </div>
                                <div class="rustic-block" style="grid-column: 1 / -1;">
                                    <label class="option-label" for="catalog_button_link">Enlace (URL)</label>
                                    <input class="config-input" type="text" id="catalog_button_link"
                                        value="<?php echo htmlspecialchars($catalogSettings['button_link'] ?? ''); ?>"
                                        placeholder="Ej: https://wa.me/5491123456789?text=Hola, info de [PRODUCTO]">
                                    <p style="font-size:0.8rem; color:#64748b; margin-top:4px;">Dejá vacío para usar la configuración por defecto de WhatsApp. Podés usar <b>[PRODUCTO]</b> para incluir el nombre.</p>
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_button_icon">Ícono</label>
                                    <select class="config-input" id="catalog_button_icon">
                                        <option value="ph-whatsapp-logo" <?php echo ($catalogSettings['button_icon'] ?? 'ph-whatsapp-logo') === 'ph-whatsapp-logo' ? 'selected' : ''; ?>>WhatsApp</option>
                                        <option value="ph-instagram-logo" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-instagram-logo' ? 'selected' : ''; ?>>Instagram</option>
                                        <option value="ph-link" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-link' ? 'selected' : ''; ?>>Enlace</option>
                                        <option value="ph-envelope" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-envelope' ? 'selected' : ''; ?>>Mail</option>
                                        <option value="ph-shopping-cart" <?php echo ($catalogSettings['button_icon'] ?? '') === 'ph-shopping-cart' ? 'selected' : ''; ?>>Carrito</option>
                                    </select>
                                </div>
                                <div class="rustic-block">
                                    <label class="option-label" for="catalog_button_color">Color del Botón</label>
                                    <select class="config-input" id="catalog_button_color">
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
                                <div class="rustic-block" style="grid-column: 1 / -1; display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                                    <div>
                                        <label class="option-label" style="margin:0;">Habilitar Botón de Acción</label>
                                        <p style="font-size:0.75rem; color:#64748b; margin:2px 0 0;">Si lo desactivás, no aparecerá ningún botón de acción (como Consultar/WhatsApp) en las tarjetas ni en el detalle de productos.</p>
                                    </div>
                                    <label class="catalog-toggle" for="catalog_show_action_button">
                                        <input type="checkbox" id="catalog_show_action_button" <?php echo ($catalogSettings['show_action_button'] ?? true) ? 'checked' : ''; ?>>
                                        <span class="catalog-toggle-slider"></span>
                                    </label>
                                </div>
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