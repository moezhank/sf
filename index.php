<?php
ini_set("display_errors", "1");
error_reporting(E_ALL);
//require configuration

/*
default docroot aplikasi
 */
$arrData     = explode('/', $_SERVER["SCRIPT_NAME"]);
$replaceChar = "/" . $arrData[count($arrData) - 1];
$docRoot     = str_replace($replaceChar, "/", $_SERVER['SCRIPT_FILENAME']);

//require mainbase
require_once 'sf/index.php';

?>
