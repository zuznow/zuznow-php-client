<?php 
require_once 'mob_config.php';
$begin_time = microtime(true);
require_once("HTTP/Request2.php");

function MOB_Fetch_Cache($cache_key){
	global $key;
	global $domain_id;
	global $server_url;
	global $cache_ttl;
	
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

function MOB_gzdecode($data){ 
   return gzinflate(substr($data,10,-8)); 
}

function MOB_Check_Header_Value($header) {
    $headers = headers_list();
    $header = trim($header,': ');
    $value = "";

    foreach ($headers as $hdr) {
		//check if header exists and get his value
        if (strpos($hdr, $header) !== false){
			$result = explode(':', $hdr);
			$value = trim($result[1]);
        }
    }
	
    return $value;
}


function MOB_Filter($str) {
	try	{	
		global $begin_time;
		global $api_key;
		global $domain_id;
		global $cache_type;
		global $server_url;
		
		//if Content-Encoding header contains gzip
		if (strpos(MOB_Check_Header_Value("Content-Encoding"),"gzip") !== false){
			$gzHandler = true;
		}
		if ($gzHandler){
			$str = MOB_gzdecode($str);
		}

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
		$request->addPostParameter('data', base64_encode($compressed_data));
		
		try
		{
			$response = $request->send();			
		}
		catch (HTTP_Request2_ConnectionException $e)
		{
			$str.= "\n<!-- \n".$e->getMessage()."\n -->";
		}	
		if (is_object($response) && 200 == $response->getStatus()) 
		{	
			$str = $response->getBody();
			$str.= "\n<!-- \nTotal time ".(microtime(true)-$begin_time)."\n -->";
			$str.= "\n<!-- \nFilter time ".(microtime(true)-$filter_begin_time)."\n -->";
			if ($gzHandler){
				$str = gzencode($str);
			}
			return $str;
		}
		else if (is_object($response) && 302 == $response->getStatus()) 
		{	
			$data_url_base = $response->getHeader("Location");
			$count = 1;
			while($count <= 10)
			{
				$data_url = $data_url_base."&key=".$api_key."&domain_id=".$domain_id."&cache_ttl=".$cache_ttl."&user_agent=".rawurlencode(MOB_Get_UA());
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
					if ($gzHandler){
						$str = gzencode($str);
					}
					return $str;
				}
				else if ((is_object($response) && 404 != $response->getStatus())) 
				{
					break;
				}
				sleep(1);
			}
		}
		if(is_object($response) )
		{
			$str.= "\n<!-- \n".$response->getStatus()."\n".$response->getBody()."\n -->";
		}
		if ($gzHandler){
			$str = gzencode($str);
		}
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
		if (isset($item[1]))
		{
		$params[$item[0]] = $item[1]; 
	} 
		else
		{
			$params[$item[0]] = ""; 
		}
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
	if ($_COOKIE['mobtest'] != "")
	{
		return true;
	}
	$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	$parsed_url = parse_url($url);
	if (isset($parsed_url["query"]))
	{
		$parsed_query = MOB_Parse_Query($parsed_url["query"]);
		if (array_key_exists("mobtest", $parsed_query))
		{
			setcookie("mobtest","true");
			return true;
		}
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
	global $mob_enabled;
	$url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	if ((!preg_match("/MOB/",$url)) && ($mob_enabled || MOB_Is_Test()))
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