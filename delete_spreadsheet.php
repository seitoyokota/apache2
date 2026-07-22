<?php
session_set_cookie_params ([
	'httponly' => true,
	'samesite' => 'Lax'
]);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
if(empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_GET['id'])|| !ctype_digit($_GET['id'])) {
    die ("Invalid spreadsheet ID.");
}
$spreadsheetId = (int) $_GET['id'];
$userId = (int) $_SESSION['user_id'];

require 'config/db.php';

$stmt = $pdo->prepare("SELECT id, user_id, title, column_headers FROM spreadsheets WHERE id = ?");
$stmt->execute([$spreadsheetId]);
$spreadsheet = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$spreadsheet) {
	die("Spreadsheet not found.");
}

if ($spreadsheet['user_id'] !=$_SESSION['user_id']) {
	die("You do not have permission to edit this spreadsheet.");
}

$stmt = $pdo->prepare("DELETE FROM spreadsheets WHERE id = ? AND user_id = ?");
$stmt->execute([$spreadsheetId,$userId]);

header("Location: dashboard.php");
exit();
?>
