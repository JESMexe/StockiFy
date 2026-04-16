<?php
require_once __DIR__ . '/../vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¿Quiénes Somos? - StockiFy</title>
    <!-- Fonts -->
    <link rel="stylesheet" href="assets/css/main.css?v=1.3">
    <link rel="stylesheet" href="assets/css/about-us.css?v=1.0">
    <!-- Icons -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <!-- Dynamic Theme -->
    <script src="assets/js/theme.js"></script>
</head>

<body class="bg-pattern">
    <header>
        <a href="index" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
        <nav id="header-nav">
            <a href="index" class="btn btn-secondary">Volver al Inicio</a>
        </nav>
    </header>

    <main>
        <div class="container">
            <!-- Hero Section -->
            <section class="about-hero">
                <h1>Nosotros le ponemos el pecho,<br><span>Vos ponés el crecimiento.</span></h1>
                <p class="hero-intro">
                    StockiFy no es solo una base de datos. Es el resultado de entender que el trabajo real sucede afuera de la pantalla, y que tu sistema tiene que seguirte el ritmo, no frenarte.
                </p>
            </section>

            <!-- Story Block -->
            <section class="content-block">
                <h2>El Origen</h2>
                <p>StockiFy nació de una necesidad compartida: dejar de pelear con los números para empezar a verlos crecer. Entendemos que decidir <strong>"ponerle el pecho"</strong> a un proyecto propio con energía y visión es el motor que mueve todo.</p>
                <p>Sabemos que el día a día puede ser una carga pesada si no contás con las herramientas correctas. Por eso construimos un espacio de trabajo crudo, eficiente y sin vueltas. Queremos que cuando entres a StockiFy, sientas que estás en tu centro de comando, donde vos tenés el control total.</p>
            </section>

            <!-- Feature Groups -->
            <section class="content-block">
                <h2>Cómo potenciamos tu trabajo</h2>
                <div class="group-grid">
                    <div class="feature-item">
                        <i class="ph ph-layout"></i>
                        <h3>Gestión Estructurada</h3>
                        <p>Tablas dinámicas diseñadas para la eficiencia. Configurá tus columnas, importá tus datos y manejá miles de artículos con la fluidez de un documento local.</p>
                    </div>
                    <div class="feature-item">
                        <i class="ph ph-swap"></i>
                        <h3>Movimiento Constante</h3>
                        <p>Entradas y salidas registradas en segundos. StockiFy actualiza tu inventario en tiempo real para que nunca pierdas el hilo de lo que pasa en tu depósito.</p>
                    </div>
                    <div class="feature-item">
                        <i class="ph ph-address-book"></i>
                        <h3>Vínculos Reales</h3>
                        <p>Clientes, proveedores y empleados en un solo lugar. Entendé el comportamiento de tus compras y ventas a través de registros claros y profesionales.</p>
                    </div>
                    <div class="feature-item">
                        <i class="ph ph-trend-up"></i>
                        <h3>Visión de Futuro</h3>
                        <p>Analíticas precisas que te muestran la rentabilidad real de tu negocio. Estadísticas diarias y mensuales para que tu próximo paso esté basado en datos, no en suposiciones.</p>
                    </div>
                </div>
            </section>

            <!-- Zoom Sections -->
            <section class="zoom-container">
                <div class="zoom-row">
                    <div class="zoom-text">
                        <h3>Dólar Oficial y MEP</h3>
                        <p>En un entorno que no espera a nadie, tus precios no pueden quedar atrás. StockiFy se sincroniza con las cotizaciones oficiales y MEP para que tu lista de precios sea un reflejo exacto de la realidad, protegiendo tus márgenes en cada venta.</p>
                    </div>
                    <div class="media-placeholder">
                        <i class="ph ph-currency-dollar"></i>
                    </div>
                </div>

                <div class="zoom-row">
                    <div class="zoom-text">
                        <h3>Alertas Inteligentes</h3>
                        <p>Tu flujo de trabajo no debería interrumpirse. Configurá avisos automáticos de stock mínimo y recibí notificaciones antes de que el problema ocurra. Nosotros cuidamos tu inventario para que vos cuides a tus clientes.</p>
                    </div>
                    <div class="media-placeholder">
                        <i class="ph ph-bell-ringing"></i>
                    </div>
                </div>

                <div class="zoom-row">
                    <div class="zoom-text">
                        <h3>Profesionalismo al Instante</h3>
                        <p>Cada transacción terminada es una oportunidad para fortalecer tu marca. Enviá comprobantes por mail directamente desde el panel con un solo click. Eficiencia que tus clientes van a notar.</p>
                    </div>
                    <div class="media-placeholder">
                        <i class="ph ph-paper-plane-tilt"></i>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="about-cta">
                <h2>¿Empezamos a trabajar?</h2>
                <p style="margin-bottom: 2.5rem; font-size: 1.25rem; opacity: 0.9;">Unite a los profesionales que eligen la eficiencia sobre la complejidad.</p>
                <a href="register" class="btn-notion">Crear mi cuenta gratis</a>
            </section>
        </div>
    </main>

    <footer class="footer" style="background-color: var(--accent-color); color: white; padding: 4rem 2rem 2rem; width: 100%; border-top: 10px solid #1b1b1b;">
        <div class="footer-container" style="max-width: 1200px; margin: 0 auto;">
            <div class="footer-brand" style="margin-bottom: 2rem;">
                <img src="assets/img/LogoE3.png" style="width: 300px; height: auto;" alt="StockiFy Logo">
            </div>
            <div class="footer-bottom" style="text-align: left; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <p style="margin-bottom: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.8);">&copy; <span id="year">2026</span> StockiFy. Todos los derechos reservados.</p>
                        <div class="footer-links" style="display: flex; gap: 20px;">
                            <a href="privacy-policy" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.9; transition: opacity 0.2s;">Política de Privacidad</a>
                            <a href="terms-of-service" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.9; transition: opacity 0.2s;">Condiciones del Servicio</a>
                            <a href="about-us" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 1; text-decoration: underline; font-weight: bold;">¿Quiénes Somos?</a>
                        </div>
                    </div>
                    <p class="footer-dev" style="margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.8);">Created by <span style="color: var(--color-white); font-weight: bold">JESMdev</span></p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById("year").textContent = new Date().getFullYear();
    </script>
</body>

</html>
