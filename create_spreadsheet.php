<?php

//session cookie flags
session_set_cookie_params([
	'httponly' =>true,
	'samesite' => 'Lax'
]);

session_start();

//makes sure this person is logged in and not here by link (idor)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

//providing a csrf token for the session if empty
if (empty($_SESSION['csrf_token'])){
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

//creating the spreadsheeet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //csrf attack prevention (checks that the post token is the same is the session token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
	die("Invalid request.");
    }
    //trims and stores title
    $title = trim($_POST['title']);
    if (strlen($title) > 100) {
        $error = "The title is too long. The maximum is 100 characters";
    }
    //stores the amount of wanted columns
    $columnCount = (int) $_POST['column_count'];
    //checks if title is empty, invalid column number,and if all good, adds columns but names cannot be empty)
    if (empty($title)) {
        $error = "Title cannot be empty.";
    } elseif ($columnCount < 1 || $columnCount > 10) {
        $error = "You must have between 1 and 10 columns.";
    } else {
        $columnHeaders = [];
        for ($i = 0; $i < $columnCount; $i++) {
	    //trims and stores column name
            $colName = trim($_POST['column_' . $i]);
            if (empty($colName)) {
                $error = "Column names cannot be empty.";
                break;
            }
	    if (strlen($colName) > 500) {
		$error = "The column name is too long. The maximum is 500 characters";
            }

            $columnHeaders[] = $colName;
        }
	//runs if there are no errors
        if (empty($error)) {
	    //connects to database
            require 'config/db.php';
	    //adds the spreadsheet for that user
            $stmt = $pdo->prepare("INSERT INTO spreadsheets (user_id, title, column_headers) VALUES (?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                json_encode($columnHeaders)
            ]);
	    //assigns an id for the spreadsheet
            $newId = $pdo->lastInsertId();
	    //allows them to view the spreadsheet
            header("Location: view_spreadsheet.php?id=" . $newId);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Spreadsheet</title>
    <link rel="stylesheet" href="style.css">
    <style>
	form {
		width: 100;
		height: 100%;
		border-sizing: border-box;
		font-size: 1.5em;
	}
    </style>
</head>
<body>
<div class="title-box">
    <h2>Create New Spreadsheet</h2>
    <!--xss attack prevention-->
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
</div>
<div class="container">
<div class="tabs">
<div class="tab">
	<p><a href="dashboard.php">Cancel / Back to Dashboard</a></p>
</div>
</div>
<div class="content">
    <form action="create_spreadsheet.php" method="POST">
	<input type="hidden" name="csrf_token" value="<?php echo $_SESSION ['csrf_token']; ?>">
        <label>Spreadsheet Title:</label><br>
        <input type="text" name="title" required><br><br>

        <label>Number of Columns (1-10):</label><br>
        <input type="number" name="column_count" id="column_count" min="1" max="10" value="3" onchange="generateColumnFields()"><br><br>

        <div id="column_fields"></div>

        <button type="submit">Create Spreadsheet</button>
    </form>
    <script>
        function generateColumnFields() {
            const count = document.getElementById('column_count').value;
            const container = document.getElementById('column_fields');
            container.innerHTML = '';
	    if (count > 10) {
		alert("Too many columns nick");
		return;
	    }
            for (let i = 0; i < count; i++) {
                container.innerHTML += 'Column ' + (i + 1) + ' name: <input type="text" name="column_' + i + '" required><br><br>';
            }
        }
        generateColumnFields();
    </script>
</div>
</div>
</body>
</html>
