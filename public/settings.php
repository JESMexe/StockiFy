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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                            </div>
                            <div class="rustic-block">
                                <label class="option-label" for="full_name">Nombre Completo</label>
                                <input class="config-input" type="text" id="full_name" name="full_name"
                                    value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>">
                            </div>
                            <div class="rustic-block">
                                <label class="option-label" for="dni">DNI / Identificación</label>
                                <input class="config-input" type="text" id="dni" name="dni"
                                    value="<?php echo htmlspecialchars($currentUser['dni'] ?? ''); ?>"
                                    placeholder="(Colocar sin puntos)">
                            </div>
                            <div class="rustic-block">
                                <label class="option-label" for="cell">Teléfono / Celular</label>
                                <input class="config-input" type="text" id="cell" name="cell"
                                    value="<?php echo htmlspecialchars($currentUser['cell'] ?? ''); ?>"
                                    placeholder="(Todo junto)">
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

                        <button type="submit" class="btn btn-primary" id="btn-guardar" disabled>Guardar Cambios de
                            Perfil</button>
                    </form>
                </div>

                <div id="soporte-container" class="hidden">
                    <div style="text-align: center; padding: 2rem;">
                        <i class="ph ph-envelope-simple-open" style="font-size: 3rem; color: var(--accent-color);"></i>
                        <h3>Centro de Ayuda</h3>
                        <p style="color: #64748b; margin-bottom: 2rem;">¿Tenés algún problema? Estamos para ayudarte.
                        </p>
                        <a href="mailto:soporte@stockify.com.ar" class="btn btn-primary">Contactar Soporte</a>
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
    </script>
</body>

</html>