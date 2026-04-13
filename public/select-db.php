<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Base de Datos - StockiFy</title>
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            background-image: url('assets/img/FondoSeleccion.svg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }

        #selection-view {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <header>
        <a href="/" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
        <nav id="header-nav">
        </nav>
    </header>

    <main>
        <div id="selection-view" class="view-container">
            <h2>Selecciona una Base de Datos</h2>
            <p>Elige con qué base de datos quieres trabajar.</p>
            <div id="db-list">
                <li>Cargando...</li>
            </div>
            <hr>
            <a href="create-db" class="btn btn-secondary">O crear una nueva</a>
        </div>
    </main>

    <script type="module" src="assets/js/database/select-db.js"></script>
</body>

</html>