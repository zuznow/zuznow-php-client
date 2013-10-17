<?php 
require_once 'mob_client.php';

if (MOB_Is_Supported())
{
	MOB_Fetch_Anonynous_Cache();
	MOB_Filter_Enable();
}

?>
