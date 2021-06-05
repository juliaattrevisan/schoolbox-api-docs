<?php

use Schoolbox\Error\Mapper as ErrorMapper;
use Zend\Uri\Http as HttpUri;

// Figure out if the URL ends with /window - and don't include it in the
// decoded URL if it does.
$isWindow        = false;
$encodedUrlParts = $this->arg;
$lastPart        = array_pop($encodedUrlParts);
if ($lastPart === 'window') {
    $isWindow = true;
} else {
    array_push($encodedUrlParts, $lastPart);
}

// It's not guaranteed that the base64 encoded string will be properly encoded.
// Fortunately we can convert spaces to +, and directory splits to / chars.
$remoteUrl = base64_decode(str_replace(' ', '+', implode('/', $encodedUrlParts)));

// For specifying the height styling of the parent frame
// Use fullscreen class if no height specified
$heightStyle = '';
$class = '';

try {
    $url = new HttpUri($remoteUrl);
} catch (InvalidArgumentException $e) {
    // Not a HTTP(s) URI
    $this->error(ErrorMapper::ERROR_PAGE_NOT_FOUND);
}
if (!$url->isValid()) {
    $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
}

//Check the security of the remoteUrl, by validating it against the safe iframe list
$secureUrl = false;
foreach (explode("\n", Config::get('safe_iframe_source_list')) as $source) {
    $source = trim($source);
    if (strlen($source) > 0) {
        $sourceEscaped = preg_quote($source, '%');
        if (preg_match('%'.$sourceEscaped.'%', $remoteUrl)) {
            $secureUrl = true;
            break;
        }
    }
}
if (!$secureUrl) {
    $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
}

$fragment = $url->getFragment();

preg_match("#zx_h=([0-9]+)#", $fragment, $matches);

if (isset($matches[1])) {
    //Assume px
    $heightStyle = 'height: ' . $matches[1] . 'px';
} else {
    //Bad or no data supplied
    $class = "fullscreen";
}

$url->setFragment('');

$query = $url->getQueryAsArray();

$secret = Config::get('remote_secret');
$time = time();
$externalUserId = $_SESSION['sid'];


$query['time'] = $time;
$query['id'] = $externalUserId;
$query['user'] = urlencode($_SESSION['username']);
$query['key'] = sha1($secret . $time . $externalUserId);

$url->setQuery($query);

if ($isWindow) {
    header('Location: ' . $url->toString());
    exit;
} else {
    $this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
    $this->setVar(array(
        'REMOTE_URL' => addslashes($url->toString()),
        'HEIGHT_STYLE' => $heightStyle,
        'FRAME_CLASS' => $class,
    ));
}
