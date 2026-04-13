<?php
require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Models\UserModel;
use Google\Client;
use Google\Service\Oauth2;

$client = new Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

if (!isset($_GET['code'])) {
    header('Location: /login?error=access_denied');
    exit;
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    $googleService = new Oauth2($client);
    $googleUser = $googleService->userinfo->get();

    $userModel = new UserModel();

    // Si el usuario ya existe en base de datos tengo que actualizar el nombre por si lo cambio en google hace poco
    $user = $userModel->findByGoogleId($googleUser->id);

    if (!$user) {
        $user = $userModel->findByEmail($googleUser->email);
        if ($user) {
            $userModel->linkGoogleAccount($user['id'], $googleUser->id);
        } else {
            $newId = $userModel->createFromGoogle([
                'email' => $googleUser->email,
                'name' => $googleUser->name,
                'google_id' => $googleUser->id
            ]);
            $user = $userModel->findById($newId);
        }
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];

    header('Location: /index');
    exit;

} catch (Exception $e) {
    error_log("Google Auth Error: " . $e->getMessage());
    header('Location: /login?error=auth_failed');
    exit;
}