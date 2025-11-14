<?php


require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();
    getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $contactData = $data;

    $stmt = $pdo->prepare("INSERT INTO contact_submissions (full_name, email, phone, subject, message) 
                                    VALUES (:full_name, :email, :phone, :subject, :message)");
    $stmt ->execute([
        ':full_name' => $contactData['full_name'],
        ':email' =>$contactData['email'],
        ':phone' => $contactData['phone'],
        ':subject' => $contactData['subject'],
        ':message' => $contactData['message']]);
    $response = ['success' => true];
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response, JSON_NUMERIC_CHECK);




