<?php
/**
 * Optimus Prime
 * Autobots, transform and roll out!
 *
 * Example usages of Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Implementation\Imagine;
use Schoolbox\IConfigLoader;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/** @var IConfigLoader $config */
$config = $kernel->getDependencyInjector()->get('config');

/* If current user is not available return permission denied */
if(! ($_SESSION['user'] instanceof Schoolbox\User)) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
}

/* Accept both id and userId params
 */
$userMapper = \Schoolbox\UserMapper::getInstance();

try {
    if (isset($_GET['id']) && (int)$_GET['id']) {
        $user = $userMapper->findById($_GET['id']);
    } else {
        $user = $userMapper->findByExternalId($_GET['userId']);
    }
} catch (\Schoolbox\Mapper\Exception $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size']);
}

try {
    $image = new Image\Processor\Standard(
        new Image\Cache\Portrait(
            $user,
            $_GET['size'],
            $_SESSION['user'],
            (bool)$config->get('photo_sis_import'),
            (bool)$config->get('protect_student_photo'),
            (int)$config->get('protect_teacher_photo')
        ),
        new Image\Transformation\Portrait(),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );

    $image->load()->stream();
}
/* When you cannot access the requested image (access denied)
 */
catch (\RuntimeException $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
}
/* Image/Portrait not found
 */
catch (\InvalidArgumentException $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 404);
}
/* All other exceptions
 * BadMethodCallException
 */
catch (Exception $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size']);
}
