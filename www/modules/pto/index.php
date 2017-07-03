<?php

use Zend\Uri\Uri;

/** @var Schoolbox\User $user */
$user = $_SESSION["user"];

new \Schoolbox\PTO\Loader($user, date("U"), Config::get('pto_school'));


// Replace placeholders in PTO url
foreach ($keys as $key => $value) {
    $remoteUrl = str_replace("[{$key}]", $value, $remoteUrl);
}

// Ensure URL is valid
$url = new Uri($remoteUrl);
if (!$url->isValid()) {
    $this->error(2);
}

// Whack it in a pretty frame
$this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
$this->setVar(array(
    'REMOTE_URL' => addslashes($url->toString()),
    'FRAME_HEIGHT' => $height,
));
