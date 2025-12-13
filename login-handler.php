<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
require_once 'vendor/autoload.php';
startSession();

/* ================= GOOGLE OAUTH CONFIG ================= */
$googleClient = new Google_Client();
$googleClient->setClientId('19401584880-f8ft6l5nclbo1dd0ops799tf3hvq0nbt.apps.googleusercontent.com'); // sesuaikan yang dapat di google cloud console
$googleClient->setClientSecret('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'); // sesuaikan yang di dapat di google cloud console
$googleClient->setRedirectUri('http://localhost/pwebbaru-main/pwebbaru-main/callback.php'); // SESUAIKAN
$googleClient->addScope('email');
$googleClient->addScope('profile');

$googleLoginUrl = $googleClient->createAuthUrl();

/* ================= REDIRECT KE GOOGLE ================= */
if (isset($_GET['google'])) {
    header('Location: ' . $googleLoginUrl);
    exit;
}

/* ================= GOOGLE CALLBACK ================= */
if (isset($_GET['code'])) {
    try {
        $token = $googleClient->fetchAccessTokenWithAuthCode($_GET['code']);

        if (isset($token['error'])) {
            throw new Exception($token['error_description']);
        }

        $googleClient->setAccessToken($token['access_token']);
        $googleService = new Google_Service_Oauth2($googleClient);
        $googleUser = $googleService->userinfo->get();

        $db = Database::getInstance()->getConnection();
        $email = $googleUser->email;

        $stmt = $db->prepare("SELECT user_id, username, full_name, avatar, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $username = explode('@', $email)[0];
            $full_name = $googleUser->name;
            $avatar = $googleUser->picture;

            $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

$stmt = $db->prepare("
    INSERT INTO users (username, email, password, full_name, avatar, role, is_active)
    VALUES (?, ?, ?, ?, ?, 'user', 1)
");
$stmt->bind_param(
    "sssss",
    $username,
    $email,
    $randomPassword,
    $full_name,
    $avatar
);

            $stmt->execute();
            $user_id = $stmt->insert_id;
        } else {
            $user = $result->fetch_assoc();
            if (!$user['is_active']) {
                setFlashMessage('danger', 'Akun Anda telah dinonaktifkan');
                redirect('index.php');
            }

            $user_id  = $user['user_id'];
            $username = $user['username'];
            $full_name = $user['full_name'];
            $avatar = $user['avatar'];
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['role'] = 'user';
        $_SESSION['avatar'] = $avatar;

        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        setFlashMessage('success', 'Login Google berhasil!');
        redirect('index.php');

    } catch (Exception $e) {
        setFlashMessage('danger', 'Login Google gagal: ' . $e->getMessage());
        redirect('index.php');
    }
}

/* ================= LOGIN BIASA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (!$email || !$password) {
        setFlashMessage('danger', 'Email dan password harus diisi');
        redirect('index.php');
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        setFlashMessage('danger', 'Email atau password salah');
        redirect('index.php');
    }

    $user = $result->fetch_assoc();

    if (!$user['is_active']) {
        setFlashMessage('danger', 'Akun Anda telah dinonaktifkan');
        redirect('index.php');
    }

    if (!verifyPassword($password, $user['password'])) {
        setFlashMessage('danger', 'Email atau password salah');
        redirect('index.php');
    }

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['avatar'] = $user['avatar'];

    setFlashMessage('success', 'Login berhasil!');
    redirect('index.php');
}

redirect('index.php');
