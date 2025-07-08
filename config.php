
<?php

define("DB_SERVER", "sql100.infinityfree.com");
define("DB_USERNAME", "if0_39415147");
define("DB_PASSWORD", "Th7SKSUGINPG3P"); // Kosongkan jika tidak ada password
define("DB_NAME", "if0_39415147_absensi_tk_pelangi");

// Attempt to connect to MySQL database
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($mysqli === false){
    die("ERROR: Could not connect. " . $mysqli->connect_error);
}

?>


