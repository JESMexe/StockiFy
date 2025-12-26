<?php
// public/api/import/execute-import.php

ob_start();

use App\Controllers\ImportController;

// 1. Configuración de Errores (Solo log, no display)
ini_set('display_errors', 0); // ¡CRÍTICO! No mostrar errores en el output
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {

    try {
        // 2. Iniciar Sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 3. Cargar Dependencias
        require_once __DIR__ . '/../../../vendor/autoload.php';

        // Aseguramos que el helper de autenticación esté cargado
        $authHelperPath = __DIR__ . '/../../../src/helpers/auth_helper.php';
        if (file_exists($authHelperPath)) {
            require_once $authHelperPath;
        } else {
            throw new Exception("No se encontró el helper de autenticación.");
        }

        // 4. Validar Método
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Método no permitido (Se espera POST).");
        }

        // 5. Ejecutar Controlador
        $controller = new ImportController();
        $controller->executeImport();

    } catch (Throwable $e) {
        // Captura cualquier error (Exception o Error Fatal) y devuelve JSON
        http_response_code(500);

        ob_clean();

        echo json_encode([
            'success' => false,
            'message' => 'Error Crítico en el Servidor: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al preparar datos: ' . $e->getMessage()]);

} finally {
    ob_end_flush();
}