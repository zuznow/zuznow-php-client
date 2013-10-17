<?php 
require_once 'client.php';

if (!$compatible)
{
	header('HTTP/1.1 404 Not Found');
	print "Missing prerequisite";
	exit;
}

if (!isset($_GET["cache_data_location"]))
{
	header('HTTP/1.1 404 Not Found');
	print "No cache_data_location";
	exit;
}
$cache_data_location = $_GET["cache_data_location"];

$charset = "UTF-8";
if (isset($_GET["charset"]))
{
	$charset =$_GET["charset"];
}

$server_url = "http://".$cache_data_location."&key=".$key."&domain_id=".$domain_id."&user_agent=".rawurlencode(MOB_Get_UA());
if (!$server_config)
{
	$server_url .= "&cache_ttl=".$cache_ttl;
}

header('Content-type: text/plain; charset='.$charset);

$request = new HTTP_Request2($server_url, HTTP_Request2::METHOD_GET);

try
{
	$response = $request->send();			
}
catch (HTTP_Request2_ConnectionException $e)
{
}	
if (is_object($response) && 200 == $response->getStatus()) 
{	
	$str = $response->getBody();
	print $str;
	exit;
}
header('HTTP/1.1 404 Not Found');
print "No data";
?>
