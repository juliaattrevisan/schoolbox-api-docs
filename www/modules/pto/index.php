<?php
use \Schoolbox\PTO\Loader as PTOLoader;
use \Schoolbox\Config\Loader as ConfigLoader;

$ptoLoader = new PTOLoader(new ConfigLoader(), $_SESSION["user"], date('U'));

try {
    // Whack it in a pretty frame
    $this->setVar([
        'contentTpl' => $this->getTwig()->render('sub/iframe.twig', [
            'url' => $ptoLoader->generateRemoteUrl()
        ])
    ]);
}
catch (\DomainException $e) {
    error(2);
}