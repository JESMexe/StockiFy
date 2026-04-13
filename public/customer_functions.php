<?php
require_once 'src/core/Database.php';

function createCustomer($user_id, $full_name, $email = null, $phone = null, $address = null, $tax_id = null) {
    global $conn;
    
    if (empty($user_id) || empty($full_name)) {
        return ['success' => false, 'message' => 'El ID de usuario y nombre completo son requeridos'];
    }

    $check_user = execConsult("SELECT id FROM users WHERE id = " . (int)$user_id);
    if (empty($check_user)) {
        return ['success' => false, 'message' => 'El usuario seleccionado no existe'];
    }

    if ($tax_id !== null) {
        $check_tax = execConsult("SELECT id FROM customers WHERE tax_id = '$tax_id'");
        if (!empty($check_tax)) {
            return [
                'success' => false,
                'message' => 'El ID Fiscal (tax_id) ya está registrado. Por favor, use un ID Fiscal diferente.',
                'error_type' => 'duplicate_tax_id'
            ];
        }
    }

    $full_name = htmlspecialchars($full_name);
    $email = $email ? htmlspecialchars($email) : null;
    $phone = $phone ? htmlspecialchars($phone) : null;
    $address = $address ? htmlspecialchars($address) : null;
    $tax_id = $tax_id ? htmlspecialchars($tax_id) : null;

    $sql = "INSERT INTO customers (user_id, full_name, email, phone, address, tax_id) VALUES (
        $user_id,
        '$full_name',
        " . ($email ? "'$email'" : "NULL") . ",
        " . ($phone ? "'$phone'" : "NULL") . ",
        " . ($address ? "'$address'" : "NULL") . ",
        " . ($tax_id ? "'$tax_id'" : "NULL") . "
    )";

    if (execConsult($sql)) {
        $result = execConsult("SELECT LAST_INSERT_ID() as last_id");
        $last_id = $result[0]['last_id'];
        
        return [
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'customer_id' => $last_id
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Error al crear el cliente'
        ];
    }
}

?>