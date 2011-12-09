<?php

$this->setFile(TEMPLATE_content, 'modules/remote/frame.html');

$remoteUrl = base64_decode($this->arg[0]);

if ( !filter_var($remoteUrl, FILTER_VALIDATE_URL) ) {
    $this->error(2);
}

$secret = 'VRMXWvcFLUNPW298';
$time = 1323305767;
$externalUserId = 24249;

$key = sha1($secret . $time . $externalUserId);

$remoteUrl = "$remoteUrl?key=$key&time=$time&id=$externalUserId";

$this->setVar('REMOTE_URL', $remoteUrl);