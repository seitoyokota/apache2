# Default virtual host configuration
In /etc/apache2/sites-available/000-default.conf
ServerAdmin webmaster@localhost 	
DocumentRoot /var/www/html

# Security implemented into the web app
Security aspects: username enumeration, sql injection, idor (insecure direct object reference) attack, rate limit, timing attacks, stored xss (cross site scripting) attack, password hashing, session id regeneration, csrf (cross site request forgery) attack, session cookie flags, rpi5 storage blowup prevention using limits for characters/rows/columns

# Programming languages used
HTML — structure of every page (login, register, dashboard, spreadsheet view)
CSS — makes it not look like 1998
PHP — everything server-side: checking login credentials, querying the database, session management, saving spreadsheet edits
MySQL — stores users and spreadsheet data

# Step by step process
Phase 1 — plumbing. Get Apache, PHP, and MySQL installed and actually talking to each other. Write a single PHP file that connects to MySQL and echoes "connected" before you write a single line of app logic. If this doesn't work, nothing downstream will, and you want to debug it in isolation.
Phase 2 — schema. Create your users, spreadsheets, and rows tables in MySQL by hand using a client (phpMyAdmin or the mysql CLI). Don't let PHP create tables dynamically yet, that's asking for trouble before you understand the shape of your own data.
Phase 3 — auth. Register page and login page. Use password_hash() and password_verify(), never anything you write yourself. Use PDO with prepared statements for every query, no exceptions, this is the SQL injection point I flagged earlier and it's non-negotiable given your line of work. On successful login, store $_SESSION['user_id']. This is the single fact every other page in your app depends on to know who's asking.
Phase 4 — dashboard. After login, query spreadsheets WHERE user_id = $_SESSION['user_id'] and list just the titles as clickable links. New account users just see an empty list plus a "create new spreadsheet" option.
Phase 5 — spreadsheet CRUD. The create/view/edit page. This is where the JSON column logic actually gets used: rendering an HTML table from column_headers and row_data, handling add/remove row, add/remove column (cap it at 10, just check count($column_headers) < 10 before allowing another add).
Phase 6 — save mechanism. You asked which is easier: submit/discard button, no contest. Autosave means JavaScript firing background requests (fetch()) every time a cell changes, debouncing so you're not hammering the server on every keystroke, and handling partial-save failure states. That's a real engineering problem. A submit button is a plain HTML form POST to a PHP script that overwrites the row. Build the button version first. If you have time left at the end and want to learn AJAX, autosave is a good stretch goal, but don't start there.

sudo apt install php libapache2-mod-php -y
sudo systemctl restart apache2
(installying php for apache)

sudo nano /var/www/html/test.php
<?php
echo "PHP is alive";
?>
http://<pi-ip>/test.php
(check php and apache connection)

sudo apt install mariadb-server -y
sudo systemctl status mariadb
(installing mysql)

sudo mysql_secure_installation
(added password so not all users on the pi can access my sql; say yes to all)

sudo mysql
(logging into mysql)

# Making SQL tables
CREATE DATABASE notesapp;
CREATE USER 'notesapp_user'@'localhost' IDENTIFIED BY 'ChangeThisPassword123!';
GRANT ALL PRIVILEGES ON notesapp.* TO 'notesapp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
(prevents cross-contamination)

sudo apt install php-mysql -y
sudo systemctl restart apache2
(install communication between php and mysql)

echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/info.php
(confirming the driver loaded)
http://<pi-ip>/info.php
Ctrl+F for "pdo_mysql" and “json”; look for “enabled”

sudo rm /var/www/html/info.php
(deleting the file so no one else can access it later)

nano /var/www/html/dbtest.php
<?php
$host = 'localhost';
$db   = 'example_database';
$user = 'example_username';
$pass = 'example_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "Connected successfully.";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
http://<pi-ip>/dbtest.php (delete once connected successfully)
(making sure php and mysql can actually talk to each other)

sudo chown -R $USER:$USER /var/www/html
(removes need for sudo)

Making sql databases
sudo mysql

USE notesapp;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
(creates the user data base logging username, password, and time account created (optional))

DESCRIBE users;
(checking that it runs correctly)

CREATE TABLE spreadsheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    column_headers JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
(creating the table for the user)

DESCRIBE spreadsheets;

CREATE TABLE spreadsheet_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spreadsheet_id INT NOT NULL,
    row_data JSON NOT NULL,
    row_order INT NOT NULL,
    FOREIGN KEY (spreadsheet_id) REFERENCES spreadsheets(id) ON DELETE CASCADE
);
(creating the rows for the tables)

SELECT id, title, created_at 
FROM spreadsheets 
WHERE user_id = ?
ORDER BY created_at DESC;

SHOW TABLES;
(verifying all 3 exist)

INSERT INTO spreadsheets (user_id, title, column_headers) 
VALUES (999, 'test', '["a","b"]');
(should show failure due to a nonexistent user;  run SHOW CREATE TABLE spreadsheets; if it actually runs)

INSERT INTO users (username, password_hash) VALUES ('testuser', 'placeholder');
INSERT INTO spreadsheets (user_id, title, column_headers) VALUES (1, 'Test Sheet', '["Name","Age"]');
SELECT * FROM spreadsheets;
DELETE FROM users WHERE username = 'testuser';
(this should run normally and delete the test run when done)
SELECT * FROM spreadsheets;
(make sure user is gone)
EXIT;
