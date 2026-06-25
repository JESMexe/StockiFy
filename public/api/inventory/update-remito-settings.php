<?php
// public/api/inventory/update-remito-settings.php
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';

use App\core\Database;
use App\helpers\ActivityLogger;

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$activeInventoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No hay un inventario seleccionado en la sesión actual.']);
        exit;
    }

    // RBAC: Verify user has Owner or Admin role in this inventory
    $role = getInventoryRole((int)$user['id'], (int)$activeInventoryId);
    if (!$role || !in_array($role['name'], ['Owner', 'Admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para modificar la configuración del remito.']);
        exit;
    }

    $db = Database::getInstance();

    // Fetch existing settings to handle old logo deletion
    $stmtSelect = $db->prepare("SELECT remito_logo_path FROM inventories WHERE id = ?");
    $stmtSelect->execute([$activeInventoryId]);
    $currentInventory = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$currentInventory) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'El inventario seleccionado no existe.']);
        exit;
    }

    $logoPath = $currentInventory['remito_logo_path'] ?? null;
    $deleteLogo = isset($_POST['delete_logo']) && (int)$_POST['delete_logo'] === 1;

    // Handle logo deletion if requested
    if ($deleteLogo && $logoPath) {
        $absolutePath = __DIR__ . '/../../' . ltrim($logoPath, '/');
        if (file_exists($absolutePath)) {
            @unlink($absolutePath);
        }
        $logoPath = null;
    }

    // Handle new logo upload
    if (isset($_FILES['remito_logo']) && $_FILES['remito_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['remito_logo'];
        
        // Validate MIME type
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('El archivo del logo debe ser una imagen válida (PNG, JPG, JPEG o WEBP).');
        }

        // Validate File Size (Max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('La imagen del logo no debe superar los 2MB de tamaño.');
        }

        // Setup uploads directory
        $uploadDir = __DIR__ . '/../../uploads/remito_logos/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('No se pudo crear el directorio de subidas en el servidor.');
            }
        }

        // Generate safe unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/webp') ? 'webp' : 'jpg');
        }
        $newFilename = 'logo_' . $activeInventoryId . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Delete previous logo file if it exists
            if ($currentInventory['remito_logo_path']) {
                $oldAbsolutePath = __DIR__ . '/../../' . ltrim($currentInventory['remito_logo_path'], '/');
                if (file_exists($oldAbsolutePath)) {
                    @unlink($oldAbsolutePath);
                }
            }
            // Set new path
            $logoPath = '/uploads/remito_logos/' . $newFilename;
        } else {
            throw new Exception('Error al mover el archivo subido al directorio de destino.');
        }
    }

    $description = isset($_POST['remito_description']) ? trim($_POST['remito_description']) : '';
    $url = isset($_POST['remito_url']) ? trim($_POST['remito_url']) : '';

    // If values are empty, save as NULL
    $dbDescription = ($description === '') ? null : $description;
    $dbUrl = ($url === '') ? null : $url;

    // Update in database
    $stmtUpdate = $db->prepare("
        UPDATE inventories 
        SET remito_logo_path = ?, remito_description = ?, remito_url = ? 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$logoPath, $dbDescription, $dbUrl, $activeInventoryId]);

    // Audit Log
    ActivityLogger::log(
        'Configuración',
        'update',
        'inventory_settings',
        (string)$activeInventoryId,
        "Actualizó configuración del remito",
        "Descripción: " . ($description ? 'Sí' : 'No') . " | URL: " . ($url ? 'Sí' : 'No') . " | Logo: " . ($logoPath ? 'Sí' : 'No'),
        (int)$activeInventoryId,
        (int)$user['id']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Configuración de remito guardada con éxito.',
        'remito_logo_path' => $logoPath,
        'remito_description' => $description,
        'remito_url' => $url
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
