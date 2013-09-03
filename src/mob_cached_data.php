<?php 
//ob_start("ob_gzhandler");

require_once 'HTTP/Request2.php';

$begin_time = microtime(true);

$key = "mykey";
$domain_id = "5";
$version = "1.00.30";
$cache_ttl = "15";

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

$server_url = "http://".$cache_data_location;

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
