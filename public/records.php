<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';

$currentUser = getCurrentUser();
if (!isset($_SESSION['user_id'])) {
    header('Location: /index');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registros - StockiFy</title>
  <script src="assets/js/theme.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/records.css">
</head>
<body>
  <header>
    <a href="/index" id="header-logo">
      <img src="assets/img/LogoE.png" alt="Stocky Logo">
    </a>
    <nav id="header-nav"></nav>
  </header>
  <main>
    <div class="registros-container">
      <a href="/dashboard" class="back-btn">← Volver al Dashboard</a>
      <h1>Registro de Modificaciones</h1>
      <div class="registros-header">
        <label for="tabla-selector">Ver registros de:</label>
        <select id="tabla-selector"></select>
      </div>
      <div id="error-msg" style="display:none"></div>
      <div class="tabla-wrapper">
        <table id="tabla-registros">
          <thead></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </main>
  <script src="assets/js/registros.js"></script>
</body>
</html>
