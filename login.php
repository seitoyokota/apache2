<?php

//session cookie flags
session_set_cookie_params([
	'httponly' => true,
	'samesite' => 'Lax'
]);
session_start();

require 'rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //stores entered username and password
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    //checks if its empty
    if (empty($username) || empty($password)) {
        die("Username and password are required.");
    }
    //connnects to database
    require 'config/db.php';
    //gets id for the username and password
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // dummy hash to prevent timing attacks
    $dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'; 
    //bcrypt hash for the string "password"

    // Always verify password — use real hash if user exists, dummy otherwise
    $hashToCheck = $user['password_hash'] ?? $dummyHash;
    $passwordValid = password_verify($password, $hashToCheck);
    //if  all username and password matches, send them to the dashboard
    if ($user && $passwordValid) {
        //regenerates a new session id
	session_regenerate_id(true);
	//gives session the user id
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        header("Location: dashboard.php");
        exit();
    } else {
	//username enumeration
        die("Invalid username or password.");
    }
}
?>
