<?php
use \Schoolbox\PTO\Loader as PTOLoader;
use \Schoolbox\Config\Loader as ConfigLoader;

$ptoLoader = new PTOLoader(new ConfigLoader(), $_SESSION["user"], date('U'), Config::get('pto_school'));

try {
    $remoteUrl = $ptoLoader->generateRemoteUrl();
}
catch (\DomainException $e) {
    $this->error(2);
}

// Whack it in a pretty frame
$this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
$this->setVar(array(
    'REMOTE_URL' => addslashes($url->toString()),
    'FRAME_HEIGHT' => $height,
));
