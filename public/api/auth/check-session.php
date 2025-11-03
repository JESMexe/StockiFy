<?php

// public/api/auth/check-session.php

session_start();

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode(['isLoggedIn' => true]);
} else {
    echo json_encode(['isLoggedIn' => false]);
}