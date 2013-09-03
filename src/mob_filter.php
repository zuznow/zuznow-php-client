<?php 
require_once 'MOB/client.php';

$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];

if (!preg_match("/wp-admin/",$url))
{
	if (MOB_Is_Smartphone())
	{
		MOB_Filter_Enable();
	}
}
?>
