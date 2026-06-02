<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de Pruebas | StockiFy</title>
    <meta name="description"
        content="Experimentá e interactuá con el gestor de inventarios y stock de StockiFy en tiempo real. Sala de pruebas interactiva 100% gratuita.">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/playground.css?v=<?= time() ?>">
    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <!-- Icons -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/theme.js"></script>
</head>

<body class="bg-pattern" style="min-height: 100vh; display: flex; flex-direction: column; margin: 0;">

    <header>
        <a href="index" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
        <nav id="header-nav" style="display: flex; gap: 10px;">
            <a href="playground" class="btn btn-secondary" style="margin:0; text-decoration: underline; font-weight: bold;">Sala de Pruebas</a>
            <a href="about-us" class="btn btn-secondary" style="margin:0;">¿Quiénes Somos?</a>
            <?php if ($currentUser): ?>
                <a href="select-db" class="btn btn-primary" style="margin:0;">Ir al Panel</a>
                <a href="logout" class="btn btn-secondary" style="margin:0;">Cerrar Sesión</a>
            <?php else: ?>
                <a href="login" class="btn btn-secondary" style="margin:0;">Iniciar Sesión</a>
                <a href="index" class="btn btn-secondary" style="margin:0;">Volver al Inicio</a>
            <?php endif; ?>
        </nav>
    </header>

    <main style="padding: 2rem 0; width: 100%; max-width: 100%; align-items: center; display: flex; flex-direction: column; flex-grow: 1;">
        <div class="playground-layout">
            
            <!-- Columna de Guía (Izquierda) -->
            <div class="playground-guide-wrapper">
                <div class="playground-guide-card">
                    <div class="playground-guide-header">
                        <div class="subtitle">Simulador del Sistema</div>
                        <h2>Sala de Pruebas</h2>
                    </div>
                    
                    <div class="playground-guide-text-container">
                        <div id="guide-text" class="playground-guide-text">
                            <!-- El JS inyecta el texto interactivo aquí -->
                        </div>
                    </div>
                    
                    <div class="playground-guide-footer">
                        <p><i class="ph-bold ph-info"></i> Interactúa con la tabla de la derecha para guiar la simulación.</p>
                    </div>
                </div>
            </div>

            <!-- Columna Interactiva (Derecha) -->
            <div class="playground-sandbox-wrapper">
                
                <!-- Controles de Vista de la Perspectiva -->
                <div class="playground-view-controls">
                    <button id="btn-toggle-flat" class="btn-view-toggle" title="Alternar inclinación de perspectiva 3D">
                        <i class="ph-bold ph-perspective"></i> <span id="view-toggle-text">Fijar Vista 2D</span>
                    </button>
                </div>

                <!-- Perspectiva y Tabla -->
                <div class="playground-perspective-wrapper">
                    <div id="playground-table-box" class="playground-table-3d-box">
                        <div class="table-container">
                            
                            <!-- Toolbar del Dashboard Real -->
                            <div class="table-header-toolbar">
                                <h2 class="playground-title" style="font-size: 1.5rem; margin: 0; white-space: nowrap; font-weight: 900; color: var(--color-black); margin-right: 15px;">Inventario de Prueba</h2>
                                <button id="btn-reset-demo" class="btn-toolbar-icon" title="Reiniciar maqueta con datos por defecto">
                                    <i class="ph ph-arrows-counter-clockwise"></i>
                                </button>
                                <button id="critical-filter-btn" title="Filtrar productos con stock crítico (bajo el mínimo)">
                                    <i class="ph ph-warning-circle" style="font-size: 1.4rem; font-weight: bold;"></i>
                                </button>
                                <div class="toolbar-search-wrapper">
                                    <input type="text" id="main-table-search" class="toolbar-search-input" placeholder="Buscar en la tabla...">
                                </div>
                                <button id="manage-columns-btn" class="btn-toolbar-icon" title="Ocultar o mostrar columnas">
                                    <i class="ph ph-eye" style="font-size: 1.2rem; font-weight: bold;"></i>
                                </button>
                                <button id="btn-export" class="btn-toolbar-action">
                                    <i class="ph ph-download-simple"></i> Exportar
                                </button>
                                <button id="btn-import" class="btn-toolbar-action">
                                    <i class="ph ph-upload-simple"></i> Importar Datos
                                </button>
                                <button id="add-row-btn" class="btn-toolbar-action primary-btn">
                                    + Añadir Fila
                                </button>
                            </div>

                            <!-- Tabla con las Columnas Reales -->
                            <div class="table-wrapper">
                                <table class="playground-table">
                                    <thead id="playground-table-head">
                                        <!-- Cabeceras renderizadas dinámicamente por JS -->
                                    </thead>
                                    <tbody id="playground-table-body">
                                        <!-- Filas renderizadas dinámicamente por JS -->
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <footer class="footer" style="background-color: var(--accent-color); color: white; padding: 4rem 2rem 2rem; width: 100%; margin-top: auto;">
        <div class="footer-container" style="max-width: 1200px; margin: 0 auto;">
            <div class="footer-brand" style="margin-bottom: 2rem;">
                <img src="assets/img/LogoE3.png" style="width: 300px; height: auto;" alt="StockiFy Logo">
            </div>
            <div class="footer-bottom" style="text-align: left; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <p style="margin-bottom: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.7);">&copy; <span id="year"></span> StockiFy. Todos los derechos reservados.</p>
                        <div class="footer-links" style="display: flex; gap: 20px;">
                            <a href="privacy-policy" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8;">Política de Privacidad</a>
                            <a href="terms-of-service" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8;">Condiciones del Servicio</a>
                            <a href="about-us" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8;">¿Quiénes Somos?</a>
                        </div>
                    </div>
                    <p class="footer-dev" style="margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.7);">Created by <span style="color: var(--color-white); font-weight: bold">JESMdev</span></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modal de Columnas (Simulado como el del Dashboard) -->
    <div id="column-manager-modal" class="modal-overlay hidden" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 style="font-size: 1.4rem; font-weight: 900; margin: 0;">Gestionar Columnas</h2>
                <button class="modal-close-btn" onclick="closeColumnManager()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto;">
                <div class="info-banner" style="background-color: #f0f4f8; border-left: 4px solid var(--accent-color); padding: 12px; border-radius: 4px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start; font-size: 0.9rem;">
                    <i class="ph-fill ph-info" style="color: var(--accent-color); font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <p style="margin: 0; line-height: 1.4; color: #475569;"><strong>Personalizá tu vista.</strong><br>
                        Desmarcá la casilla para ocultar la columna en el inventario activo.</p>
                    </div>
                </div>
                <div id="column-manager-list" class="column-manager-list" style="display: flex; flex-direction: column; gap: 8px;">
                    <!-- Checkboxes generados dinámicamente -->
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 1rem 1.5rem; background-color: #f9f9f9; border-top: 1px solid #eee; border-radius: 0 0 12px 12px;">
                <button class="btn btn-secondary" onclick="closeColumnManager()" style="margin: 0; padding: 8px 16px;">Cancelar</button>
                <button class="btn btn-primary" onclick="saveColumnPreferences()" style="margin: 0; padding: 8px 16px; background-color: var(--accent-color); color: white; border-color: var(--color-black);">Guardar</button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.getElementById("year").textContent = new Date().getFullYear();
    </script>
    <script src="assets/js/playground.js" defer></script>
</body>

</html>
