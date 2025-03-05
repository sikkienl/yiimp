<?php
 //https://tradeogre.com/api/v1/markets
function tradeogre_api_query($method, $params='')
{
	$uri = "https://tradeogre.com/api/v1/{$method}";
	if (!empty($params)) $uri .= "/{$params}";
 	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 	$execResult = strip_tags(curl_exec($ch));
 	$obj = json_decode($execResult, true);
 	return $obj;
}

function tradeogre_api_user($method, $url_params = [], $request_method='GET', $returnType='object') {
	$timedrift = 3;
	
	if (empty(EXCH_TRADEOGRE_KEY) || empty(EXCH_TRADEOGRE_SECRET)) return false;

	$base = 'https://'.EXCH_TRADEOGRE_KEY. ':' .	$secret = EXCH_TRADEOGRE_SECRET.'@tradeogre.com'; $path = '/api/v1/'.$method;

	$request = '';

	if (is_array($url_params)) {
		ksort($url_params);
		$request = http_build_query($url_params, '', '&');
	} elseif (is_string($url_params)) {
		$request = $url_params;
	}

	$payload = '';
	if ($request_method == 'POST') {
		$uri = $base.$path;
		$payload = $request;
	}
	else {
		if ($request != '')
			$uri = $base.$path.'?'.$request;
		else
			$uri = $base.$path;
	}

	$http_request = new cHTTP();
	$http_request->setURL($uri);

	if ($request_method == 'POST') {
		$http_request->setPostfields($url_params);
	}
	$http_request->setUserAgentString('Mozilla/4.0 (compatible; tradeogre API client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	$http_request->setFailOnError(false);
	$data = $http_request->execRequest();
	if ($returnType == 'object')
		$res = json_decode($data);
	else
		$res = json_decode($data,true);

	$status = $http_request->fResult['HTTP_Code'];
	
	if($status >= 300) {
		debuglog("tradeogre: $method failed ($status) ".strip_data($data));
		$res = false;
	}

	return $res;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
