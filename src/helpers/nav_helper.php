<?php
// src/helpers/nav_helper.php

function render_nav($type = 'dashboard'): void
{
    $baseUrl = '/public/'; // Ajusta según tu estructura de carpetas

    echo '<header>';
    echo '    <a href="' . $baseUrl . 'dashboard.php" id="header-logo">';
    echo '        <img src="' . $baseUrl . 'assets/img/LogoE.png" alt="StockiFy Logo">';
    echo '    </a>';
    echo '    <nav id="header-nav">';

    switch ($type) {
        case 'dashboard':
            // Navegación principal dentro de la App
            echo '        <a href="' . $baseUrl . 'settings.php" class="btn btn-secondary"><i class="ph ph-gear"></i> Configuración</a>';
            echo '        <a href="' . $baseUrl . 'logout.php" class="btn btn-secondary"><i class="ph ph-sign-out"></i> Salir</a>';
            break;

        case 'configuration':
            // Navegación cuando ya estás en configuración
            echo '        <a href="' . $baseUrl . 'dashboard.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Volver al Dashboard</a>';
            echo '        <a href="' . $baseUrl . 'logout.php" class="btn btn-secondary"><i class="ph ph-sign-out"></i> Salir</a>';
            break;

        case 'auth':
            // Navegación simplificada para pantallas de login/registro o errores
            echo '        <a href="' . $baseUrl . 'logout.php" class="btn btn-secondary"><i class="ph ph-sign-out"></i> Cerrar Sesión</a>';
            break;

        case 'select-db':
            echo '        <a href="' . $baseUrl . 'logout.php" class="btn btn-secondary"><i class="ph ph-sign-out"></i> Cerrar Sesión</a>';
            break;

        case 'empty':
            // Solo el logo, útil para procesos críticos o setups
            break;
    }

    echo '    </nav>';
    echo '</header>';
}