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
tideways_enable(TIDEWAYS_FLAGS_CPU + TIDEWAYS_FLAGS_MEMORY);

use Alaress\Image;
use Alaress\Image\Implementation\Imagine;

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

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
    $profiler_namespace = 'schoolbox';
    $xhprof_data = tideways_disable();

    $xhprof_runs = new XHProfRuns_Default();
    $run_id = $xhprof_runs->save_run($xhprof_data, $profiler_namespace);
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
/* Invalid file type
 */
catch (\DomainException $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 404);
}
/* All other exceptions
 * BadMethodCallException
 */
catch (Exception $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 500);
}

