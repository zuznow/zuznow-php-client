<?php 
require_once 'HTTP/Request2.php';

$begin_time = microtime(true);

$key = "mykey";
$domain_id = "5";
$version = "1.00.30";
$cache_ttl = "15";
$server_url = "http://proxy5.zuznow.com/API/mobilize.php";
$aync_response = true;

function MOB_Filter($str) {
	try	{	
		global $begin_time;
		global $key;
		global $domain_id;
		global $version;
		global $cache_ttl;
		global $server_url;
		global $aync_response;
		
		$filter_begin_time = microtime(true);
		
		$compressed_data = gzcompress($str);
		
		$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		
		$request = new HTTP_Request2($server_url, HTTP_Request2::METHOD_POST);
		$request->addPostParameter('key', $key);
		$request->addPostParameter('domain_id', $domain_id);
		$request->addPostParameter('version', $version);
		$request->addPostParameter('url', $url);
		if ($_SERVER['REQUEST_METHOD'] === 'GET')
		{
			$request->addPostParameter('cache-key', md5($url));
			$request->addPostParameter('cache-ttl', $cache_ttl);
		}
		$request->addPostParameter('data', $compressed_data);
		
		$rules = realpath(dirname(__FILE__))."/"."rules.txt";
		if (file_exists($rules))
		{
			$compressed_rules = gzcompress(file_get_contents($rules));
			$request->addPostParameter('rules', $compressed_rules);
		}
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
			$str.= "\n<!-- \nTotal time ".(microtime(true)-$begin_time)."\n -->";
			$str.= "\n<!-- \nFilter time ".(microtime(true)-$filter_begin_time)."\n -->";
			return $str;
		}
		else if (is_object($response) && 302 == $response->getStatus()) 
		{	
			$data_url = $response->getHeader("Location");
			if (!$aync_response || ($_SERVER['REQUEST_METHOD'] !== 'GET'))
			{
				while(true)
				{
					$request = new HTTP_Request2($data_url, HTTP_Request2::METHOD_GET);
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
						$str.= "\n<!-- \nTotal time ".(microtime(true)-$begin_time)."\n -->";
						$str.= "\n<!-- \nFilter time ".(microtime(true)-$filter_begin_time)."\n -->";
						return $str;
						exit;
					}
					else if (is_object($response) && 404 != $response->getStatus()) 
					{
						break;
					}
				}
			}
			else
			{
				$data_url = str_replace("http://","",$data_url);
				$data_url = rawurlencode($data_url);
				
				$async_load_template = realpath(dirname(__FILE__))."/"."async_load_template.txt";
				if (file_exists($async_load_template))
				{
					header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
					header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
					header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
					header("Cache-Control: post-check=0, pre-check=0", false);
					header("Pragma: no-cache");
					$str = file_get_contents($async_load_template);
					$str = str_replace("%%DATAURL%%",$data_url,$str);
				}
				else			
				{
					$str = "<html><head></head><body>\n<script>var cache_url ='/MOB/get_cached_data.php?cache_data_location=";
					$str.= $data_url;
					$str.="';\n";
					$str.="</script>\n</body></html>";
				}
				$str.= "\n<!-- \nTotal time ".(microtime(true)-$begin_time)."\n -->";
				$str.= "\n<!-- \nFilter time ".(microtime(true)-$filter_begin_time)."\n -->";
				return $str;
			}
		}
		$str.= "\n<!-- \n".$response->getStatus()."\n".$response->getBody()."\n -->";
	}
	catch (Exception $e)
	{
		$str.= "\n<!-- \n".$e->getMessage()."\n -->";
	}		
    return $str;
}

function MOB_Is_Smartphone()
{ 
	if(isset($_SERVER["HTTP_USER_AGENT"])){
		if(preg_match("/(iPhone|iPod)/i",$_SERVER["HTTP_USER_AGENT"])) {
			return true;
		}
		if(preg_match("/(Android)/i",$_SERVER["HTTP_USER_AGENT"])) {
			if(preg_match("/(Mobile)/i",$_SERVER["HTTP_USER_AGENT"])) {
				return true;
			}
		}
	}
	return false;
}

function MOB_Filter_Enable()
{
	$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	if (!preg_match("/MOB/",$url))
	{
		ob_start("MOB_Filter");
	}
}

?>
