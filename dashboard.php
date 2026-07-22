<?php

//session cookie flags
session_set_cookie_params([
	'httponly' =>true,
	'samesite' => 'Lax'
]);

session_start();
//checks to see if the user is logged in and not here by link (idor)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
//connects to database
require 'config/db.php';
//gets all of the titles and ids that belongs to the user
$stmt = $pdo->prepare("SELECT id, title, created_at FROM spreadsheets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$spreadsheets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
<style>
    body {
        margin: 0;
        font-family: Courier;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .title-box {
        background-color: lightblue;
        border: 3px solid #333;
        border-radius: 8px;
        padding: 3px;
        text-align: center;
        font-size: 1.5em;
        font-weight: bold;
    }

    .container {
        display: flex;
        flex: 1;
        height: 100%;
    }

    .tabs {
        width: 10%;
        background-color: #e9ecef;
        border-right: 3px solid #333;
        display: flex;
        flex-direction: column;
    }

    .tab {
        padding: 15px;
        border-bottom: 1px solid #999;
        transition: background-color ;
    }

    .content {
        width: 90%;
        padding: 20px;
        background-color: #fff;
    }
    a {
	text-decoration: none;
	color: black;
    }
    a:hover {
	font-weight: bold;
    }
</style>
</head>
<body>
<div class="title-box">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
</div>
<div class="container">
<div class="tabs">
<div class="tab">
    <p><a href="create_spreadsheet.php">Create New Spreadsheet</a></p>
</div>
<div class="tab">
    <p><a href="logout.php">Log out</a></p>
</div>
</div>
<div class="content">
    <h3>Your Spreadsheets</h3>
    <?php if (empty($spreadsheets)): ?>
        <p>You don't have any spreadsheets yet.</p>
    <?php else: ?>
        <ul>
        <?php foreach ($spreadsheets as $sheet): ?>
            <li>
		<!--xss attack prevention-->
                <a href="view_spreadsheet.php?id=<?php echo $sheet['id']; ?>">
                    <?php echo htmlspecialchars($sheet['title']); ?>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</div>
</body>
</html>
