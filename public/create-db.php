<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Base de Datos - StockiFy</title>
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css"> </head>
<body>
<header>
    <a href="/" id="header-logo">
        <img src="assets/img/LogoE.png" alt="StockiFy Logo">
    </a>
</header>
<div class="auth-wrapper">
    <div class="auth-form-container">
        <div class="auth-header">
            <h1>Crea tu Primera Base de Datos</h1>
            <p>Define la estructura de tu nuevo inventario.</p>
        </div>

        <form id="createDbForm" class="auth-form">
            <div class="form-group">
                <label for="dbNameInput">Nombre de la Base de Datos:</label>
                <input type="text" id="dbNameInput" placeholder="Ej: Inventario de Verano">
            </div>
            <div class="form-group">
                <label for="columnsInput">Nombres de las Columnas (separados por coma):</label>
                <textarea id="columnsInput" rows="3" placeholder="Ej: ID, Nombre, Precio, Stock"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Crear Base de Datos</button>
        </form>

        <div id="message"></div>
    </div>
</div>

<script type="module" src="assets/js/database/create-db.js"></script>
</body>
</html>