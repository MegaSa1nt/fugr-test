<?php
error_reporting(0);
if(!isset($db)) global $db; // Если в каком-то файле уже был вызван этот файл, то просто вернуть его

if(empty($db)) {
	require __DIR__."/../config/database.php";
	
	$db = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password, array(PDO::ATTR_PERSISTENT => true));
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	function exception_error_handler($errno, $errstr, $errfile, $errline) {
		throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
	}
	set_error_handler("exception_error_handler");
}
?>