<?php
/*session_set_cookie_params([
	‘httponly’ =>true,
	‘samesite’ => ‘Lax’
]);*/

session_start();
$ip = $_SERVER['REMOTE_ADDR'];

if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = [];
}

// removes old attempts older than 15 minutes
$_SESSION['attempts'] = array_filter($_SESSION['attempts'], function($time) {
    return $time > time() - 900; // 900 seconds = 15 minutes
});

// check attempts
if (count($_SESSION['attempts']) >= 5) {
    http_response_code(429); // too many requests
    die("Too many attempts. Try again later.");
}

// record this attempt
$_SESSION['attempts'][] = time();
?>
