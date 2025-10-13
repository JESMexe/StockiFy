<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-form-container">
        <div class="auth-header">
            <h1>Registrarse</h1>
            <p>Comienza a gestionar tu inventario en minutos.</p>
        </div>

        <form id="registerForm" class="auth-form">
            <div class="form-group">
                <label for="full_name">Nombre Completo</label>
                <input type="text" id="full_name" name="full_name" placeholder="Ej: Juan Pérez">
            </div>
            <div class="form-group">
                <label for="username">Nombre de Usuario</label>
                <input type="text" id="username" name="username" placeholder="Ej: juanperez" required>
            </div>
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" placeholder="tu@email.com" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" required>
            </div>
            <button type="submit" class="btn btn-primary">Crear Cuenta</button>
        </form>

        <div id="message"></div>

        <footer class="auth-footer">
            <p>¿Ya tienes una cuenta? <a href="/login.php">Inicia sesión aquí</a></p>
        </footer>
    </div>
</div>

<script type="module" src="/assets/js/user/register.js"></script>
</body>
</html>