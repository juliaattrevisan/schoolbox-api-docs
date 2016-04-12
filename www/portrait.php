<?php
/**
 * Optimus Prime
 * Autobots, transform and roll out!
 *
 * Example usages of Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Image;
use Alaress\Image\Implementation\Imagine;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

require_once '../include/Bootstrap.php';

//
// Alaress_Image_Cache_Portrait needs to know if someone is logged in.
session_start();

/* If current user is not available return permission denied */
if(! ($_SESSION['user'] instanceof Schoolbox\User)) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
}

try {
    $image = new Image\Processor\Standard(
        new Image\Cache\Portrait(trim($_GET['userId']), $_GET['size'], $_SESSION['user']),
        new Image\Transformation\Portrait(),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );

    $image->load()->stream();

} catch (SchoolboxAccessDeniedException $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);

} catch (SchoolboxNotFoundException $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 404);

} catch (SchoolboxException $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 500);

}
