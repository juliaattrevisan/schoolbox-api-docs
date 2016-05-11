<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../../library/Bootstrap.php';

if($argc < 3) {
	die("Usage: php {$argv[0]} <sessionID> <count>\n");
}

$session = $argv[1];
$count   = intval($argv[2]);

// remove existing
Database::getInstance()->exec("UPDATE news_items SET item_deleted = 1");


$rss = new Alaress_Rss_Stream(Cfg::get('welcome_rss_url'));
$size = $count ?: Cfg::get('welcome_rss_limit');
$rss->setLimit($size);
$rss->stream();

$allRss = $rss->getOutput();

foreach($allRss as $item) {
	$title = Scrub::text($item['title']);
	$body = Scrub::html($item['content'] ? $item['content'] : $item['description']);
	$url = 'http://' . Cfg::get('site_hostname') . '.' . Cfg::get('site_domain') . '/news/modifyForm.php';

	$aurl = parse_url($url);
	$host = $aurl['host'];
	$path = $aurl['path'];

	if(!($socket = stream_socket_client("tcp://$host:80", $errorCode, $errorMessage, 300, STREAM_CLIENT_CONNECT))) {
		echo "[ ] ($errorCode) $errorMessage\n";
		continue;
	}

	$boundary = '---------------------' . substr( md5( rand(0, 32000) ), 0, 10 );

	$parameterList = array(
		'newsID'                  => '',
		'heading'                 => $title,
		'body'                    => $body,
		'item_topic_id'           => 1,
		'group'                   => array(1, 2, 3, 4, 5),
		'campus'                  => array(1, 2),
		'item_datetime_published' => date('d/m/Y'),
		'item_datetime_expires'   => date('d/m/Y', strtotime('1 week')),
	);

	fputs($socket, "POST $path HTTP/1.0\n");
	fputs($socket, "Host: $host\n");
	fputs($socket, "Cookie:PHPSESSID=$session\n");
	fputs($socket, "Content-type: multipart/form-data; boundary=$boundary\n");

	$postData = '';

	foreach ( $parameterList as $parameterName => $parameterValue ) {

		if(is_array($parameterValue)) {
			$parameterName .= '[]';
			foreach($parameterValue as $paramValue) {
				$postData .= "--$boundary\n";
				$postData .= "Content-Disposition: form-data; name=\"$parameterName\"\n\n$paramValue\n";
			}
		}
		else {
			$postData .= "--$boundary\n";
			$postData .= "Content-Disposition: form-data; name=\"$parameterName\"\n\n$parameterValue\n";
		}
	}

	$postData .= "--$boundary--\n";

	fputs($socket, "Content-length: ".strlen($postData)."\n\n");
	fputs($socket, $postData);

	$response = '';

	while ( !feof($socket) ) {
		$response .= fread($socket, 1);
	}

	fclose($socket);

	echo "[ ] Response: $response\n";
}
