<?php

//session cookie flags
session_set_cookie_params([
	'httponly' =>true,
	'samesite' => 'Lax'
]);

session_start();

//makes sure this person is actually logged in and not just here by link (idor)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

//makes sure id is only integers and is not empty
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Invalid spreadsheet ID.");
}
$spreadsheetId = (int) $_GET['id'];

//connects to database
require 'config/db.php';
//gets basic info about spreadsheet for checking
$stmt = $pdo->prepare("SELECT id, user_id, title, column_headers FROM spreadsheets WHERE id = ?");
$stmt->execute([$spreadsheetId]);
$spreadsheet = $stmt->fetch(PDO::FETCH_ASSOC);

//checks if a spreadsheet exists with that id
if (!$spreadsheet) {
    die("Spreadsheet not found.");
}

//makes sure the id entered matches the current user's id
if ($spreadsheet['user_id'] != $_SESSION['user_id']) {
    die("You do not have permission to view this spreadsheet.");
}

$columnHeaders = json_decode($spreadsheet['column_headers'], true);
//gets all of the info for the spreadsheet
$stmt = $pdo->prepare("SELECT id, row_data FROM spreadsheet_rows WHERE spreadsheet_id = ? ORDER BY row_order ASC");
$stmt->execute([$spreadsheetId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <!--xss attack prevention-->
    <title><?php echo htmlspecialchars($spreadsheet['title']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
	table {
		width: 100%;
		height: 100%;
        	border-collapse: collapse;
		border: 2px solid black;
		text-align: center;
		table-layout: fixed;
	}
	td, th {
		word-wrap: break-word;
		overflow-wrap: break-word;
	}
	a {
		color: black;
	}
    </style>
</head>
<body>
<div class="title-box">
    <h2><?php echo htmlspecialchars($spreadsheet['title']); ?></h2>
</div>
<div class="container">
<div class="tabs">
<div class="tab">
    <p><a href="dashboard.php">Back to Dashboard</a></p>
</div>
<div class="tab">
    <p><a href="edit_spreadsheet.php?id=<?php echo $spreadsheet['id']; ?>">Edit this spreadsheet</a></p>
</div>
<div class="tab">
    <p><a href="delete_spreadsheet.php?id=<?php echo $spreadsheet['id']; ?>">Delete this spreadsheet</a></p>
</div>
</div>
<div class="content">
    <table border="1" cellpadding="5">
        <tr>
	    <!--xss attack prevention-->
            <?php foreach ($columnHeaders as $header): ?>
                <th><?php echo htmlspecialchars($header); ?></th>
            <?php endforeach; ?>
        </tr>

        <?php foreach ($rows as $row): ?>
            <?php $rowData = json_decode($row['row_data'], true); ?>
            <tr>
		<!--xss attack prevention-->
                <?php foreach ($rowData as $cell): ?>
                    <td><?php echo htmlspecialchars($cell); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
</body>
</html>
