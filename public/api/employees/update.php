<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/EmployeeModel.php';

use App\Models\EmployeeModel;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    
$inventoryId = $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) { echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']); exit; }
$input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id']) || empty($input['name'])) {
        echo json_encode(['success'=>false, 'message'=>'Datos incompletos']); exit;
    }

    $email = $input['email'] ?? null;
    $categoryId = $input['category_id'] ?? null;

    if ($email && $categoryId) {
        $db = \App\core\Database::getInstance();
        $stmtCheck = $db->prepare("
            SELECT ic.role_id 
            FROM inventory_collaborators ic
            JOIN users u ON ic.user_id = u.id
            WHERE ic.inventory_id = ? AND u.email = ? AND ic.status = 'active'
            LIMIT 1
        ");
        $stmtCheck->execute([$inventoryId, $email]);
        $collabRoleId = $stmtCheck->fetchColumn();
        if ($collabRoleId && (int)$collabRoleId === 2) {
            $categoryId = null; // Force null to prevent Admin category assignments
        }
    }

    $model = new EmployeeModel();
    $success = $model->updateEmployee(
        $input['id'],
        $user['id'],
        $input['name'],
        $input['dni'] ?? null,
        $input['phone'] ?? null,
        $email,
        $inventoryId,
        $categoryId,
        $input['custom_data'] ?? null
    );

    echo json_encode(['success' => $success]);

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}