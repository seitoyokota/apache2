<?php
//processes only if it is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'rate_limit.php';
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    //checking if its empty or invalid
    if (empty($username) || empty($password)) {
        die("<p>Username and password cannot be empty.</p> <img src='videoplayback.gif' alt='Error' style='height:100%; width:100%'>");
    }
    if (strlen($username) > 50) {
	die("Username is too long. The maximum is 50 characters.");
    }
    //checks for password length
    if (strlen($password) < 15) {
        die("<p>Invalid password: make sure password includes at least 15 characters, 1 letter, 1 number, and 1 special character.</p><img src='videoplayback.gif' alt='Error' style='height:100%; width:100%'><audio autoplay loop><source src='icecream.mp4' type='audio/mpeg'></audio>");
    }
    //checks for at least one letter
    if (!preg_match('/[A-Za-z]/', $password)) {
        die("<p>Invalid password: make sure password includes at least 15 characters, 1 letter, 1 number, and 1 special character.</p><img src='videoplayback.gif' alt='Error' style='height:100%; width:100%'><audio autoplay loop><source src='icecream.mp4' type='audio/mpeg'></audio>");
    }

    //checks for at least one digit
    if (!preg_match('/\d/', $password)) {
        die("<p>Invalid password: make sure password includes at least 15 characters, 1 letter, 1 number, and 1 special character.</p><img src='videoplayback.gif' alt='Error' style='height:100%; width:100%'><audio autoplay loop><source src='icecream.mp4' type='audio/mpeg'></audio>");
    }

    //checks for at least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        die("<p>Invalid password: make sure password includes at least 15 characters, 1 letter, 1 number, and 1 special character.</p><img src='videoplayback.gif' alt='Error' style='height:100%; width:100%'><audio autoplay loop><source src='icecream.mp4' type='audio/mpeg'></audio>");
    }
    if (strlen($password) > 255) {
        die("Password is too long. The maximum is 255 characters.");
    }


    //hashing the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    //connecting to the database
    require 'config/db.php';

    //sql injection defense
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    //tries to see if it can successfully store username and password
    try {
        $stmt->execute([$username, $password_hash]);
    } catch (PDOException $e) {
        //error code 23000 = covers unique username rule
        if ($e->getCode() == 23000) {
            die("That username is already taken.");
        } else {
            die("Something went wrong: " . $e->getMessage());
        }
    }

    //starts a session and logs them straight in after registering
    session_start();
    //gives user id to the session (part of idor)
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['username'] = $username;

    header("Location: dashboard.php");
    exit();
}
?>
