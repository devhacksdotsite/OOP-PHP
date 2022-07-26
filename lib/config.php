<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);
session_start();

date_default_timezone_set('America/Phoenix');

// load DB config
$GLOBALS["mysqli"] = mysqli_connect('host', 'username', 'password', 'dbname');

if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  exit();
}

include_once ("class.php");
?>