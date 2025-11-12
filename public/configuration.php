<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockiFy</title>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/show_password.js"></script>
    <script src="assets/js/config-changes-monitoring.js"></script>
    <script src="assets/js/config-option-selection.js"></script>
    <script src="assets/js/modif-buttons-controls.js"></script>
    <script type="module" src="assets/js/email-change-handler.js"></script>
    <script type="module" src="assets/js/change-password-handler.js"></script>
    <script src="assets/js/modif-reg-handler.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
</head>

<?php

    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/auth_helper.php';
    $currentUser = getCurrentUser();

    if (!isset($_SESSION['user_id'])) {
        header('Location: /StockiFy/index.php');
    }
?>

<body id="page-index">
    <div id="grey-background" class="hidden">
        <p id="msj-bubble" class="view-container"></p>
    </div>
    <header>
        <a href="/StockiFy/index.php" id="header-logo">
            <img src="assets/img/LogoE.png" alt="Stocky Logo">
        </a>
        <nav id="header-nav">
        </nav>
    </header>

    <main class="text-left">
        <div class="flex-row" style="margin: 3rem 0 3rem 0;">
            <div id="options-config-container">
                <div class="btn btn-option-selected" id="btn-config-cuenta">
                    <p>Mi Cuenta</p>
                </div>
                <div class="btn" id="btn-config-modifs">
                    <p>Registro de Modificaciones</p>
                </div>
                <div class="btn" id="btn-config-soporte">
                    <p>Soporte</p>
                </div>
            </div>
            <div class="flex-column all-center" id="config-container">
                <form class="flex-column justify-left align-center" method="post" action="configuration.php" id="form-micuenta">
                    <label for="nombre" style="margin-top: 0">Nombre</label>
                    <input class="config-input" type="text" id="nombre" name="name" value=<?php echo $currentUser['full_name']?>>

                    <label for="apellido">Apellido</label>
                    <input class="config-input" type="text" id="apellido" name="surname" value=<?php echo $currentUser['full_name']?>>

                    <label for="email">Email</label>
                    <input class="input-locked config-input" type="email" id="email" name="email" value=<?php echo $currentUser['email']?> readonly>
                    <p class="btn btn-modificar" style="margin-bottom: 0" id="btn-modif-email">Modificar Email</p>

                    <label for="contraseña-hidden">Contraseña</label>
                    <div class="flex-row all-center" style="gap: 0.3rem;">
                        <input class="input-locked" type="text" id="contraseña-fake" value="************" disabled>
                        <input class="input-locked hidden config-input" type="text" id="contraseña" name="password" value=<?php echo $currentUser['password']?> readonly>
                        <div id='btn-password' class="btn flex-row all-center"><img src="./assets/img/password-hidden.png" id="pass-img"></div>
                    </div>
                    <p class="btn btn-modificar" id="btn-modif-pass">Modificar Contraseña</p>

                    <button class="btn" id="btn-guardar" disabled >Guardar Cambios</button>
                </form>
                <div id="registro-modifs-container" class="flex-column all-center hidden">
                </div>
                <div id="soporte-container" class="hidden">
                    <div class="view-container">Soporte</div>
                </div>
            </div>
        </div>
        <div class="view-container flex-column justify-left align-center hidden" id="modif-form-container">
            <p id="return-btn" class="return-btn">Volver</p>
            <form style="margin-top: 2rem" id="email-form" class="hidden">
                <label for="new-email" class="text-left"><h2>Nuevo E-Mail</h2></label>
                <input type="email" id="new-email" name='new-email' placeholder="ejemplo@gmail.com" required>
                <button type="submit" class="btn" style="margin-top: 8rem;">Confirmar</button>
            </form>
            <form style="margin-top: 2rem" id="code-form" class="hidden">
                <label for="code" class="text-left"><h2>Codigo Recibido (Seis dígitos)</h2></label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" placeholder="......" required>
                <button type="submit" class="btn" style="margin-top: 8rem;">Confirmar</button>
            </form>
            <div class="hidden" id="save-email-container">
                <h2>¿Desea modificar el email asociado a su cuenta?</h2>
                <h3 style="margin-top: 2rem">Nuevo email: </h3><span id="new-email-text"></span>
                <button style="margin-top: 3rem" class="btn btn-primary" id="save-email-btn">Confirmar</button>
            </div>
            <form class="hidden" id="change-password-form">
                <label style= "margin-top: 0" for="old-password"><h2>Contraseña Actual</h2></label>
                <input type="password" id="old-password">
                <label for="new-password"><h2>Contraseña Nueva</h2></label>
                <input type="password" id="new-password" name="new-password">
                <label for="confirm-new-password"><h2>Confirmar Contraseña Nueva</h2></label>
                <input type="password" id="confirm-new-password">
                <label for="confirm-new-password" style="font-size: 0.7rem; margin-top: 0.5rem">Verificar que la contraseña nueva sea distinta a la ya existente y que todos los campos sean correctos.</label>
                <button type="submit" style="margin-top: 5rem" class="btn btn-primary" id="save-password-btn" disabled>Confirmar</button>
            </form>
        </div>
    </main>
    <div class="flex-column hidden" id="info-modif-container">
        <div class="flex-row justify-right" style="width: 100%"><p id="reg-return" class="return-btn">Volver</p></div>

    </div>
</body>
</body>
</html>