<?php

define('INTRANET_site', 'intranet.wenona.nsw.edu.au');
define('INTRANET_encryption_key', 'ABCD1234');

// This file contains functions specific to Wenona

function xorEncrypt($key, $string)
{
	$keylength = strlen($key);

	for($i=0; isset($string[$i]); $i++){
		$string[$i] = $string[$i] ^ $key[$i % $keylength];
	}
	
	return $string;
}

?>
