#!/usr/bin/env bash
VAR=$(grep "\$httpClient = new GuzzleClient(\['curl' => Proxy\\\\Server::getInstance()->buildCurlOptions(), 'verify' => false\]);"  library/Alaress/Cache/Abstract.php)
if [ -z "$VAR" ];
then sed -i -e "s/\$httpClient = new GuzzleClient(\['curl' => Proxy\\\\Server::getInstance()->buildCurlOptions()\]);/\$httpClient = new GuzzleClient(\['curl' => Proxy\\\\Server::getInstance()->buildCurlOptions(), 'verify' => false\]);/g" library/Alaress/Cache/Abstract.php;
else echo 'do nothing';
fi