<?php
use \Schoolbox\PTO\Loader as PTOLoader;
use \Schoolbox\Config\Loader as ConfigLoader;

$ptoLoader = new PTOLoader(new ConfigLoader(), $_SESSION["user"], date('U'));

try {
    $remoteUrl = $ptoLoader->generateRemoteUrl();

    // Whack it in a pretty frame
    $this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
    $this->setVar(array(
        'REMOTE_URL' => addslashes($remoteUrl),
        'FRAME_HEIGHT' => $height,
    ));
}
catch (\DomainException $e) {
    error(2);
}