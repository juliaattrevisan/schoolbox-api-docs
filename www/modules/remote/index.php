<?php

use Zend\Uri\Uri;

// It's not guaranteed that the base64 encoded string will be properly encoded.
// Fortunately we can convert spaces to +, and directory splits to / chars.
$remoteUrl = base64_decode(str_replace(' ', '+', implode('/', $this->arg)));

// is this specifying a height for the parent frame?
$height = '460px';

$url = new Uri($remoteUrl);
if (!$url->isValid()) {
    $this->error(2);
}

$fragment = $url->getFragment();
if(stripos($fragment, 'zx_h=') !== false) {
    preg_match("#zx_h=([0-9]*(?:%|px|&))#", $fragment, $matches);
    if(isset($matches[1])) {
        $height = str_replace('&', '', $matches[1]);
    }
    $url->setFragment('');
}

$query = $url->getQueryAsArray();

$secret = Config::get('remote_secret');
$time = time();
$externalUserId = $_SESSION['sid'];


$query['time'] = $time;
$query['id'] = $externalUserId;
$query['user'] = urlencode($_SESSION['username']);
$query['key'] = sha1($secret . $time . $externalUserId);

$url->setQuery($query);

if ($this->arg[1] == 'window') {
    header('Location: '.$url->toString());
} else {
    $this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
    $this->setVar(array(
        'REMOTE_URL' => addslashes($url->toString()),
        'FRAME_HEIGHT' => $height,
    ));
}

