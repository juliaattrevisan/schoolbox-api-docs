<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

define('DEFAULT_ICON_SIZE', 128);
define('MAX_AGE', 2419200);

/* Enforce sitepage context
 */
if (empty($this)) {
    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/mime');
}

$this->setAjax();

$filenames = [];

try {

    /* Always need two args for the mime
     */
    if (\count($this->arg) !== 2) {
        throw new Schoolbox\Mapper\Exception('No mime specified');
    }

    /* Enforce dimensions
     */
    $size = empty($_GET['size']) ? DEFAULT_ICON_SIZE : (int)$_GET['size'];

    $root = __DIR__ . '/../images/mime';
    $base = "{$root}/";

    // special case -_-
    if ($this->arg === ['folder']) {
        $filenames[] = __DIR__ . '/../images/icons/128x128/folder.png';
    } else {
        $filenames[] = "{$root}/128x128/mime.png";
        $filenames[] = "{$base}/mime.png";
    }

    /** @var Mimetype $mime */
    $mime = MimetypeMapper::getInstance()->findOneByColumnList([
        'mime' => strtolower(implode('/', $this->arg)),
        'enable' => 'true'
    ]);

    $icon = $mime->getIconFile();

    $filenames[] = "{$base}/{$icon}";
} catch (Schoolbox\Mapper\Exception $e) {
    // Show an empty icon
    $filenames[] = "{$base}/none.svg";
}

/* Check each of the possible filenames for what we chose
 *
 * Start with the one associated by MimetypeMapper which is synonymous for Allowed Files configuration
 * then gradually fall back to other files
 *
 * ...though why you hardcoded them in the backwards order then array_reverse() here is going to
 * remain a mystery to me but ok
 */
foreach (array_reverse($filenames) as $filename) {

    /* Progressively degrade icon choice
     */
    if (!file_exists($filename)) {
        continue;
    }

    $fp = fopen($filename, 'rb');

    $maxAge = MAX_AGE; // 28 days

    // send the right headers
    header('Content-Type: image/svg+xml');
    header('Content-Length: ' . filesize($filename));
    header('Pragma: public');
    header("Cache-Control: public, max-age={$maxAge}");
    header('Expires: ' . date('r', $_SERVER['REQUEST_TIME'] + $maxAge));

    // dump the picture and stop the script
    fpassthru($fp);

    exit;
}

error(41);
