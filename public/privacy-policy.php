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
    <title>Política de Privacidad | StockiFy</title>
    <link rel="stylesheet" href="assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/promo-bar.css?v=<?= time() ?>">
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/promo-bar.js" defer></script>
    <style>
        .legal-content {
            max-width: 800px;
            margin: 4rem auto;
            padding: 2.5rem;
            background: var(--color-white);
            border: var(--border-strong);
            box-shadow: 10px 10px 0px var(--color-gray);
            transition: all 0.3s ease;
        }

        .legal-content:hover {
            box-shadow: 12px 12px 0px var(--accent-color);
            transform: translate(-2px, -2px);
        }

        .legal-content h1 {
            margin-bottom: 2rem;
            font-size: 2.5rem;
            color: var(--accent-color);
        }

        .legal-content h2 {
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }

        .legal-content p {
            margin-bottom: 1rem;
            color: #444;
        }

        .legal-content ul {
            margin-bottom: 1rem;
            padding-left: 2rem;
        }

        .legal-content li {
            margin-bottom: 0.5rem;
            color: #444;
        }

        .legal-link-email {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: bold;
        }

        .legal-link-email:hover {
            text-decoration: underline;
        }

        /* Footer Styles match index.php */
        .footer {
            background-color: var(--color-black);
            color: white;
            padding: 4rem 2rem 2rem;
            width: 100%;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-brand img {
            width: 300px;
            height: auto;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body class="bg-pattern" id="page-privacy">
    <header>
        <a href="index" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
        <nav id="header-nav" style="display: flex; gap: 10px;">
            <?php if ($currentUser): ?>
                <a href="select-db" class="btn btn-primary" style="margin:0;">Ir al Panel</a>
            <?php endif; ?>
            <a href="index" class="btn btn-secondary" style="margin:0;">Volver al Inicio</a>
        </nav>
    </header>
    <?php
    $showPromoBar = !$currentUser || (isset($currentUser['subscription_active']) && (int)$currentUser['subscription_active'] === 0);
    if ($showPromoBar):
    ?>
        <div class="promo-secondary-bar">
            <div class="promo-bg-carousel">
                <div class="carousel-track">
                    <!-- JS rellena esto -->
                </div>
            </div>
            <div class="promo-main-content">
                <div class="promo-text-center">Potenciá tu negocio al máximo nivel: Probá el Acceso Total de
                    <strong>StockiFy</strong> <span class="text-accent">GRATIS</span> por 30 días.
                </div>
                <div class="promo-button-wrapper">
                    <a href="register" class="btn-promo">Probar Ahora</a>
                </div>
            </div>
            <button class="btn-close-promo" id="closePromo">Cerrar</button>
        </div>
    <?php endif; ?>

    <main>
        <div class="legal-content">
            <h1>Política de Privacidad</h1>
            <p>Última actualización: 16 de abril de 2026</p>

            <h2>1. Información que Recopilamos</h2>
            <p>En StockiFy, recopilamos información personal básica necesaria para proveer nuestros servicios de gestión
                de inventario, incluyendo:</p>
            <ul>
                <li>Nombre completo y dirección de correo electrónico cuando creas una cuenta.</li>
                <li>Datos de Google Auth (si eliges este método de inicio de sesión).</li>
                <li>Número de teléfono (si optas por notificaciones de WhatsApp).</li>
                <li>Datos de inventario y transacciones comerciales que ingreses voluntariamente en la plataforma.</li>
            </ul>

            <h2>2. Uso de la Información</h2>
            <p>Utilizamos tus datos únicamente para:</p>
            <ul>
                <li>Proveer y mantener el funcionamiento de tu panel de control.</li>
                <li>Enviar notificaciones críticas de seguridad (cambio de contraseña, OTP).</li>
                <li>Enviar alertas automáticas de stock o cierres de caja solicitados por el usuario.</li>
                <li>Mejorar la experiencia del usuario y corregir errores técnicos.</li>
            </ul>

            <h2>3. Protección de Datos</h2>
            <p>Implementamos medidas de seguridad robustas para proteger tu información. Las contraseñas están cifradas
                con algoritmos de última generación y los accesos a la base de datos están restringidos. No compartimos
                ni vendemos tus datos a terceros.</p>

            <h2>4. Tus Derechos</h2>
            <p>Tienes derecho a acceder, rectificar o eliminar tus datos personales en cualquier momento desde la
                sección de ajustes de tu cuenta o contactando a nuestro soporte.</p>

            <h2>5. Contacto</h2>
            <p>Si tienes preguntas sobre esta política, puedes contactarnos en: <a href="mailto:soporte@stockify.com.ar"
                    class="legal-link-email">soporte@stockify.com.ar</a></p>
        </div>
    </main>

    <footer class="footer" style="background-color: var(--accent-color); margin-top: 4rem;">
        <div class="footer-container">
            <div class="footer-brand">
                <img src="assets/img/LogoE3.png" alt="StockiFy Logo">
            </div>
            <div class="footer-bottom"
                style="text-align: left; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <p style="margin-bottom: 5px; font-size: 0.9rem; color: rgba(255,255,255,0.7);">&copy; 2026 StockiFy.
                    Todos los derechos reservados.</p>
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div class="footer-links" style="display: flex; gap: 20px;">
                        <a href="privacy-policy" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8; transition: opacity 0.2s;">Política de Privacidad</a>
                        <a href="terms-of-service" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8; transition: opacity 0.2s;">Condiciones del Servicio</a>
                        <a href="about-us" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8; transition: opacity 0.2s;">¿Quiénes Somos?</a>
                    </div>
                    <p class="footer-dev" style="margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.7);">Created by
                        <span style="color: var(--color-white); font-weight: bold">JESMdev</span>
                    </p>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>