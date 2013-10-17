<?php 
require_once 'config.php';

$compatible = true;
if(!@include_once("HTTP/Request2.php")) 
{
	print "HTTP/Request2.php is required";
	$compatible = false;
}

$begin_time = microtime(true);

function MOB_Fetch_Cache($cache_key){
	global $key;
	global $domain_id;
	global $server_url;
	global $cache_ttl;
	global $compatible;
	
	if (!$compatible) return;
	
	$my_url = $server_url."get_cached_data.php?key=".$key."&domain_id=".$domain_id."&cache_key=".$cache_key."&cache_ttl=".$cache_ttl."&user_agent=".rawurlencode(MOB_Get_UA());
	$request = new HTTP_Request2($my_url, HTTP_Request2::METHOD_GET);
	try
	{
		$response = $request->send();
	}
	catch (HTTP_Request2_ConnectionException $e)
	{
		return "";
	}	
	if (is_object($response) && 200 == $response->getStatus()) 
	{	
		return $response->getBody();
	}
	return "";
}

function MOB_Fetch_Anonynous_Cache(){
	global $begin_time;
	global $cache_type;
	global $compatible;
	
	if (!$compatible) return;
	
	if ($cache_type == "anonymous" && $_SERVER['REQUEST_METHOD'] === 'GET')
	{
		$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		$str = MOB_Fetch_Cache(md5($url));
		if ($str != "")
		{
			$str.= "\n<!--\nServed from Anonymous cache\nTotal time ".(microtime(true)-$begin_time)."\n -->";
			print $str;
			exit;
		}
	}
}

function MOB_Filter($str) {
	try	{	
		global $begin_time;
		global $api_key;
		global $domain_id;
		global $version;
		global $cache_type;
		global $cache_ttl;
		global $server_url;
		global $aync_response;
		global $compatible;
		
		if (!$compatible) return $str;
		
		$filter_begin_time = microtime(true);
		
		$compressed_data = gzencode($str);
		
		$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		
		$request = new HTTP_Request2($server_url."mobilize.php", HTTP_Request2::METHOD_POST);
		$request->addPostParameter('key', $api_key);
		$request->addPostParameter('domain_id', $domain_id);
		$request->addPostParameter('url', $url);
		if (MOB_Is_Test())
		{
			$request->addPostParameter('force', 'true');
		}
		$request->addPostParameter('user_agent', MOB_Get_UA());
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
		{
			$request->addPostParameter('ajax', 'true');
		}
		else
		{
			$request->addPostParameter('ajax', 'false');
		}	
		if (!MOB_Is_Secure_Connection())
		{
			if ($cache_type == "anonymous" && $_SERVER['REQUEST_METHOD'] === 'GET')
			{
				$request->addPostParameter('cache_key', md5($url));
			}
			else if ($cache_type != "none")
			{
				$request->addPostParameter('cache_key', md5($str));
			}
		}
		if (!$server_config)
		{
			$request->addPostParameter('version', $version);
			$request->addPostParameter('cache_ttl', $cache_ttl);
		}
		$request->addPostParameter('data', base64_encode($compressed_data));
		
		if (!$server_config)
		{
			$rules = realpath(dirname(__FILE__))."/"."rules.txt";
			if (file_exists($rules))
			{
				$compressed_rules = gzcompress(file_get_contents($rules));
				$request->addPostParameter('rules', $compressed_rules);
			}
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
				$count = 1;
				while(true)
				{
					$data_url.="&key=".$key."&domain_id=".$domain_id."&cache_ttl=".$cache_ttl."&user_agent=".rawurlencode(MOB_Get_UA());
					$request = new HTTP_Request2($data_url, HTTP_Request2::METHOD_GET);
					try
					{
						$response = $request->send();
						$count++;
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
					else if ((is_object($response) && 404 != $response->getStatus())) 
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

function MOB_Parse_Query($query) { 
	$queryParts = explode('&', $query); 

	$params = array(); 
	foreach ($queryParts as $param) { 
		$item = explode('=', $param); 
		$params[$item[0]] = $item[1]; 
	} 

	return $params; 
} 
function MOB_Get_UA()
{
	if (MOB_Is_Test())
	{
		return "iPhone";
	}
	return $_SERVER['HTTP_USER_AGENT'];
}
function MOB_Is_Test()
{
	$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	$parsed_url = parse_url($url);
	$parsed_query = MOB_Parse_Query($parsed_url["query"]);
	if (array_key_exists("mobtest", $parsed_query))
	{
		setcookie("mobtest","true");
		return true;
	}
	if ($_COOKIE['mobtest'] != "")
	{
		return true;
	}
	return false;
}
function MOB_Is_Supported()
{ 
	if(isset($_SERVER["HTTP_USER_AGENT"]))
	{
		if(preg_match("/(iPhone|iPod)/i",$_SERVER["HTTP_USER_AGENT"])) {
			return true;
		}
		if(preg_match("/(Android)/i",$_SERVER["HTTP_USER_AGENT"])) {
			return true;
		}
	}
	if (MOB_Is_Test())
	{
		return true;
	}	
	return false;
}

function MOB_Filter_Enable()
{	
	global $compatible;
	
	if (!$compatible) return;
	$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	if (!preg_match("/MOB/",$url))
	{
		ob_start("MOB_Filter");
	}
}

function MOB_Is_Secure_Connection()
{
	if(isset($_SERVER['HTTPS']))
	{
		if ($_SERVER["HTTPS"] == "on") 
		{
			return true;
		}
	}
	return false;
}

?>
