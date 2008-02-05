<?php
/***
	HTTP Request class
	$Id$

	This class allows HTTP requests to be made to other servers. 
	Original code from info at b1g dot de on http://us2.php.net/manual/en/function.fopen.php
	modified by Stormtrooper and slightly enhanced.

***/

class HTTPRequest {
var $_fp;
var $_url;
var $_method;
var $_postdata;
var $_host;
var $_protocol;
var $_uri;
var $_port;
var $_error;
var $_headers;
var $_text;
var $errstr;
var $errno;

// constructor
function HTTPRequest($url, $method="GET", $data="") {
	$this->_url = $url;
	$this->_method = $method;
	$this->_postdata = $data;
	$this->_scan_url();
}

// scan url
function _scan_url() {
	$req = $this->_url;
	$pos = strpos($req, '://');
	$this->_protocol = strtolower(substr($req, 0, $pos));
	$req = substr($req, $pos+3);
	$pos = strpos($req, '/');
	if($pos === false) $pos = strlen($req);
	$host = substr($req, 0, $pos);
      
	if(strpos($host, ':') !== false) {
		list($this->_host, $this->_port) = explode(':', $host);
	} else {
		$this->_host = $host;
		$this->_port = ($this->_protocol == 'https') ? 443 : 80;
	}

	$this->_uri = substr($req, $pos);
	if ($this->_uri == '') $this->_uri = '/';
}
  
// returns all headers. only call after download()
function getAllHeaders() {
	return $this->_headers;
}

// return the value of a single header
function header($key) {
	return array_key_exists($key, $this->_headers) ? $this->_headers[$key] : null;
}

function status() {
	return $this->_error;
}

function text() {
	return $this->_text;
}

// download contents of an URL to a string
function download($follow_redirect = true) {
	$crlf = "\r\n";
      
	// generate request
	$req = $this->_method . ' ' . $this->_uri . ' HTTP/1.0' . $crlf .
		'Host: ' . $this->_host . $crlf . 
		$crlf;
	if ($this->_postdata) $req .= $this->_postdata;

	// fetch
	$this->_fp = fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port, $this->errno, $this->errstr, 10);

	if ($this->_fp) {
		fwrite($this->_fp, $req);
		while (is_resource($this->_fp) && $this->_fp && !feof($this->_fp)) {
			$response .= fread($this->_fp, 1024);
		}
		fclose($this->_fp);
	} else {
		$response = '';
	}
      
	// split header and body
	$pos = strpos($response, $crlf . $crlf);
	if ($pos === false) return $response;

	$header = substr($response, 0, $pos);
	$body = substr($response, $pos + 2 * strlen($crlf));
      
	// parse headers
	$this->_headers = array();
	$lines = explode($crlf, $header);
	list($zzz, $this->_error, $zzz) = explode(" ", $lines[0], 3); unset($zzz);
	foreach ($lines as $line) {
		if (($pos = strpos($line, ':')) !== false) {
			$this->_headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
		}
	}

	// redirection?
	if (isset($headers['location']) and $follow_redirect) {
		$http = new HTTPRequest($headers['location']);
		return $http->download($http);
	} else {
		$this->_text = $body;
		return $body;
	}
}

} // end HTTPRequest

?>