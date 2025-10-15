<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<header>
    <a href="/" id="header-logo">
        <img src="assets/img/LogoE.png" alt="StockiFy Logo">
    </a>
</header>
<div class="auth-wrapper">
    <div class="auth-form-container">
        <div class="auth-header">
            <h1>Bienvenido de vuelta</h1>
            <p>Ingresá tus credenciales para acceder a tu panel.</p>
        </div>
        <form id="loginForm" class="auth-form">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" placeholder="tu@email.com" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary">Ingresar</button>
        </form>
        <div id="message"></div>
        <footer class="auth-footer">
            <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
        </footer>
    </div>
</div>
<script type="module" src="assets/js/user/login.js"></script>
</body>
</html>