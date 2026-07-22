<?php

//session cookie flags
session_set_cookie_params([
	'httponly' =>true,
	'samesite' => 'Lax'
]);

session_start();

//checks the user is logged in and not here by link (idor)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

//assigns a csrf token for the session if empty
if(empty($_SESSION['csrf_token'])){
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

//makes sure the id has integers only and is not empty
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Invalid spreadsheet ID.");
}
$spreadsheetId = (int) $_GET['id'];

//connected to database
require 'config/db.php';
//gets basic spreadsheet info for checking
$stmt = $pdo->prepare("SELECT id, user_id, title, column_headers FROM spreadsheets WHERE id = ?");
$stmt->execute([$spreadsheetId]);
$spreadsheet = $stmt->fetch(PDO::FETCH_ASSOC);

//checks if spreadsheet exists for that spreadsheet id
if (!$spreadsheet) {
    die("Spreadsheet not found.");
}
//makes sure the user id matches the current user
if ($spreadsheet['user_id'] != $_SESSION['user_id']) {
    die("You do not have permission to edit this spreadsheet.");
}

//gets all of the current columns and rows
$columnHeaders = json_decode($spreadsheet['column_headers'], true);

$stmt = $pdo->prepare("SELECT id, row_data, row_order FROM spreadsheet_rows WHERE spreadsheet_id = ? ORDER BY row_order ASC");
$stmt->execute([$spreadsheetId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$validRowIds = [];
foreach ($rows as $r) {
    $validRowIds[] = (int) $r['id'];
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //checks to see if the post csrf token matches the session csrf token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
	die("Invalid request.");
    }
    $action = $_POST['action'] ?? '';
    //if the action is to add a column
    if ($action === 'add_column') {
        if (count($columnHeaders) >= 10) {
            $message = "You already have the maximum of 10 columns.";
        } else {
	    //adds a new column
            $columnHeaders[] = "New Column";
	    //updates the column headers in sql
            $update = $pdo->prepare("UPDATE spreadsheets SET column_headers = ? WHERE id = ?");
            $update->execute([json_encode($columnHeaders), $spreadsheetId]);
	    //updates the rows in sql
            $updateRow = $pdo->prepare("UPDATE spreadsheet_rows SET row_data = ? WHERE id = ?");
            //adds rows to match the current amount
	    foreach ($rows as $r) {
                $rowData = json_decode($r['row_data'], true);
                $rowData[] = "";
                $updateRow->execute([json_encode($rowData), $r['id']]);
            }
	    //goes back for another action
            header("Location: edit_spreadsheet.php?id=" . $spreadsheetId);
            exit();
        }
    }
    //if the action is to remove a column
    elseif ($action === 'remove_column') {
        if (count($columnHeaders) <= 1) {
            $message = "You must have at least 1 column.";
        } else {
	    //removes a column
            array_pop($columnHeaders);
	    //updates the column headers in sql
            $update = $pdo->prepare("UPDATE spreadsheets SET column_headers = ? WHERE id = ?");
            $update->execute([json_encode($columnHeaders), $spreadsheetId]);
	    //updates the rows in sql
            $updateRow = $pdo->prepare("UPDATE spreadsheet_rows SET row_data = ? WHERE id = ?");
            //deletes rows to match the current amount
	    foreach ($rows as $r) {
                $rowData = json_decode($r['row_data'], true);
                array_pop($rowData);
                $updateRow->execute([json_encode($rowData), $r['id']]);
            }
	    //goes back for another action
            header("Location: edit_spreadsheet.php?id=" . $spreadsheetId);
            exit();
        }
    }
    //if action is to add a row
    elseif ($action === 'add_row') {
	if(count($rows) >= 25) {
		$message = "You've reached the maximum of 25 rows.";
	} else {
	//adds empty columns to the bottom to match current amount of columns
        $emptyRow = array_fill(0, count($columnHeaders), "");
	
	//counts the amount of rows
        $maxOrder = 0;
        foreach ($rows as $r) {
            $maxOrder = max($maxOrder, (int) $r['row_order']);
        }
	
	//updates values and adds 1 row in sql
        $insert = $pdo->prepare("INSERT INTO spreadsheet_rows (spreadsheet_id, row_data, row_order) VALUES (?, ?, ?)");
        $insert->execute([$spreadsheetId, json_encode($emptyRow), $maxOrder + 1]);
	//goes back for another action
        header("Location: edit_spreadsheet.php?id=" . $spreadsheetId);
        exit();
	}
    }
    //if the action is to remove a row
    elseif ($action === 'remove_row') {
	//creates an int location of the row that will be removed
        $rowIdToRemove = (int) ($_POST['row_id'] ?? 0);

	//if the the row id is valid, removes the row
        if (in_array($rowIdToRemove, $validRowIds, true)) {
            $delete = $pdo->prepare("DELETE FROM spreadsheet_rows WHERE id = ? AND spreadsheet_id = ?");
            $delete->execute([$rowIdToRemove, $spreadsheetId]);
	}
	//goes back for another action
        header("Location: edit_spreadsheet.php?id=" . $spreadsheetId);
        exit();
    }
    //if the action is the save changes button
    elseif ($action === 'save') {
        $newTitle = trim($_POST['title'] ?? '');
	if (strlen($pnewTitle) > 100) {
        	die("Title is too long. The maximum is 100 characters.");
    	}
	//if the title is not empty, updtaes the title
        if (!empty($newTitle)) {
            $update = $pdo->prepare("UPDATE spreadsheets SET title = ? WHERE id = ?");
            $update->execute([$newTitle, $spreadsheetId]);
        }
	//makes an array for the submitted headers(columns)
        $submittedHeaders = $_POST['headers'] ?? [];
        $cleanHeaders = [];
	//trims and stores the new headers
        foreach ($submittedHeaders as $h) {
	    if (strlen($h) > 500) {
		die("One of the boxes has too many characters. The maximum is 500 characters");
	    }
            $cleanHeaders[] = trim($h);
        }
	//if the numbers match and has no errors, updates the column headers
        if (count($cleanHeaders) === count($columnHeaders)) {
            $update = $pdo->prepare("UPDATE spreadsheets SET column_headers = ? WHERE id = ?");
            $update->execute([json_encode($cleanHeaders), $spreadsheetId]);
        }
	//makes an array for the submitted cells(rows)
        $submittedCells = $_POST['cell'] ?? [];
        $updateRow = $pdo->prepare("UPDATE spreadsheet_rows SET row_data = ? WHERE id = ? AND spreadsheet_id = ?");
	//checks the row id is valid
        foreach ($submittedCells as $rowId => $cellValues) {
            $rowId = (int) $rowId;

            if (!in_array($rowId, $validRowIds, true)) {
                continue;
            }
	    //trims it of extra space
            $cleanRow = [];
            foreach ($cellValues as $cell) {
                if(strlen($cell) > 500) {
			die("One of the boxes has too many characters. The maximum is 500 characters.");
		}
	        $cleanRow[] = trim($cell);
            }
	    //updates the rows in sql
            $updateRow->execute([json_encode($cleanRow), $rowId, $spreadsheetId]);
        }
	//goes back to view the spreadsheet
        header("Location: view_spreadsheet.php?id=" . $spreadsheetId);
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit <?php echo htmlspecialchars($spreadsheet['title']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
	a {
		color: black;
	}
    </style>
</head>
<body>
<div class="title-box">
    <h2>Edit Spreadsheet</h2>
</div>
<div class="container">
<div class="tabs">
<div class="tab">
    <p><a href="view_spreadsheet.php?id=<?php echo $spreadsheet['id']; ?>">Cancel / Back to View</a></p>
    <!--xss attack prevention-->
    <?php if (!empty($message)): ?>
        <p style="color:red;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
</div>
</div>
<div class="content">
    <form method="POST" action="edit_spreadsheet.php?id=<?php echo $spreadsheetId; ?>">
	<!-- assigns a csrf token for the post request that is the same as the current session csrf token -->
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION ['csrf_token']; ?>">
	<input type="hidden" name="action" value="save">
	<!--xss attack prevention-->
        <label>Title:</label><br>
        <input type="text" name="title" value="<?php echo htmlspecialchars($spreadsheet['title']); ?>"><br><br>

        <table border="1" cellpadding="5">
            <tr>
		<!--xss attack prevention-->
                <?php foreach ($columnHeaders as $header): ?>
                    <th>
                        <input type="text" name="headers[]" value="<?php echo htmlspecialchars($header); ?>">
                    </th>
                <?php endforeach; ?>
                <th>Row Actions</th>
            </tr>

            <?php foreach ($rows as $row): ?>
                <?php $rowData = json_decode($row['row_data'], true); ?>
                <tr>
                    <?php foreach ($rowData as $cell): ?>
                        <td>
			    <!--xss attack prevention-->
                            <input type="text" name="cell[<?php echo $row['id']; ?>][]" value="<?php echo htmlspecialchars($cell); ?>">
                        </td>
                    <?php endforeach; ?>
                    <td>
                        <button type="submit" form="deleteRow<?php echo $row['id']; ?>">
                            Delete Row
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <br>
        <button type="submit">Save Changes</button>
    </form>

    <?php foreach ($rows as $row): ?>
        <form method="POST" action="edit_spreadsheet.php?id=<?php echo $spreadsheetId; ?>" id="deleteRow<?php echo $row['id']; ?>">
	    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION ['csrf_token']; ?>">
            <input type="hidden" name="action" value="remove_row">
            <input type="hidden" name="row_id" value="<?php echo $row['id']; ?>">
        </form>
    <?php endforeach; ?>

    <br>

    <form method="POST" action="edit_spreadsheet.php?id=<?php echo $spreadsheetId; ?>" style="display:inline;">
	<input type="hidden" name="csrf_token" value="<?php echo $_SESSION ['csrf_token']; ?>">
        <button type="submit" name="action" value="add_column">Add Column</button>
    </form>

    <form method="POST" action="edit_spreadsheet.php?id=<?php echo $spreadsheetId; ?>" style="display:inline;">
	<input type="hidden" name="csrf_token" value="<?php echo $_SESSION ['csrf_token']; ?>">
        <button type="submit" name="action" value="remove_column">Remove Column</button>
    </form>

    <form method="POST" action="edit_spreadsheet.php?id=<?php echo $spreadsheetId; ?>" style="display:inline;">
	<input type="hidden" name="csrf_token" value="<?php echo $_SESSION ['csrf_token']; ?>">
        <button type="submit" name="action" value="add_row">Add Row</button>
    </form>
</div>
</div>
</body>
</html>
