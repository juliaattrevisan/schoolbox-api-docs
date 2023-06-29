#!/usr/bin/env bash
VAR=$(grep "\$httpClient = new GuzzleClient(\['curl' => Proxy\\\\Server::getInstance()->buildCurlOptions(), 'verify' => false\]);"  library/Alaress/Cache/Abstract.php)
if [ -z "$VAR" ];
then echo 'do nothing';
else sed -i -e "s/\$httpClient = new GuzzleClient(\['curl' => Proxy\\\\Server::getInstance()->buildCurlOptions(), 'verify' => false\]);/\$httpClient = new GuzzleClient(\['curl' => Proxy\\\\Server::getInstance()->buildCurlOptions()\]);/g" library/Alaress/Cache/Abstract.php;
fi