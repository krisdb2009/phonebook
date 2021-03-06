<?php 
/*
Phone Book
----------
Index Router
----------
Dylan Bickerstaff
----------
Routes requests to the proper APIs.
*/
set_time_limit(300);
error_reporting(E_ALL);
ini_set('html_errors', false);
ini_set('display_errors', 1);
$singlePointEntry = true;
$prettyPrintIfRequested = 0;
header('phonebook-api-created-by: Dylan Bickerstaff');
header('phonebook-api-version: 2.4');
ob_start();
define('SCHEMA', json_decode(file_get_contents(__DIR__.'/schema.cfg.json'), true));
if(isset($_POST['api']) && !empty($_POST['api'])) {
	header('Content-Type: application/json');
	require('./reqs/database.run.php');
	if(isset($_POST['prettyprint'])) {
		$prettyPrintIfRequested = JSON_PRETTY_PRINT;
	}
	if($_POST['api'] == 'import') {
		if(isset($_POST['legacy'])) {
			require('./reqs/import.legacy.api.php');
		} else {
			require('./reqs/import.api.php');
		}
	} elseif($_POST['api'] == 'export') {
		require('./reqs/export.api.php');
	} elseif($_POST['api'] == 'search') {
		require('./reqs/search.api.php');
	} elseif($_POST['api'] == 'stats') {
		require('./reqs/statistics.api.php');
	} elseif($_POST['api'] == 'feedback') {
		require('./reqs/feedback.api.php');
	} elseif($_POST['api'] == 'translations') {
		require('./reqs/translations.api.php');
	} elseif($_POST['api'] == 'misc') {
		require('./reqs/misc.api.php');
	}
} elseif(isset($_GET['db'])) {
	require('./reqs/dbadmin.run.php');
} else {
	echo file_get_contents('documentation.api.htm');
}
header('phonebook-api-response-time: '.(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']));
ob_end_flush();
?>