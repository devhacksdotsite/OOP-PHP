<?php
	include_once ("./lib/config.php");
	ob_start(); 
	$_SESSION["path"] = $_SERVER['REQUEST_URI'];
	$wd = new WD();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OOP Example</title>
</head>
<body>