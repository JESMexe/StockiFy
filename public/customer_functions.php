<?php
require_once 'config/DataBase.php';

/**
 * Crea un nuevo cliente en la base de datos
 * @param int $user_id ID del usuario que crea el cliente
 * @param string $full_name Nombre completo del cliente
 * @param string $email Email del cliente (opcional)
 * @param string $phone Teléfono del cliente (opcional)
 * @param string $address Dirección del cliente (opcional)
 * @param string $tax_id ID fiscal del cliente (opcional)
 * @return array Retorna un array con 'success' y 'message'
 */
function createCustomer($user_id, $full_name, $email = null, $phone = null, $address = null, $tax_id = null) {
    global $conn;
    
    // Validar campos requeridos
    if (empty($user_id) || empty($full_name)) {
        return ['success' => false, 'message' => 'El ID de usuario y nombre completo son requeridos'];
    }

    // Verificar que el usuario existe
    $check_user = execConsult("SELECT id FROM users WHERE id = " . (int)$user_id);
    if (empty($check_user)) {
        return ['success' => false, 'message' => 'El usuario seleccionado no existe'];
    }

    // Verificar si el tax_id ya existe (solo si se proporciona uno)
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

    // Escapar caracteres especiales para prevenir SQL injection
    $full_name = htmlspecialchars($full_name);
    $email = $email ? htmlspecialchars($email) : null;
    $phone = $phone ? htmlspecialchars($phone) : null;
    $address = $address ? htmlspecialchars($address) : null;
    $tax_id = $tax_id ? htmlspecialchars($tax_id) : null;

    // Construir la consulta SQL
    $sql = "INSERT INTO customers (user_id, full_name, email, phone, address, tax_id) VALUES (
        $user_id,
        '$full_name',
        " . ($email ? "'$email'" : "NULL") . ",
        " . ($phone ? "'$phone'" : "NULL") . ",
        " . ($address ? "'$address'" : "NULL") . ",
        " . ($tax_id ? "'$tax_id'" : "NULL") . "
    )";

    // Ejecutar la consulta usando execConsult
    if (execConsult($sql)) {
        // Obtener el último ID insertado con una consulta separada
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

// Ejemplo de uso:
/*
$resultado = createCustomer(
    1,                          // user_id
    'Juan Pérez',               // full_name
    'juan@email.com',          // email
    '1234567890',              // phone
    'Calle Principal 123',     // address
    'TAX123'                   // tax_id
);

if ($resultado['success']) {
    echo "Cliente creado con éxito. ID: " . $resultado['customer_id'];
} else {
    echo "Error: " . $resultado['message'];
}
*/
?>