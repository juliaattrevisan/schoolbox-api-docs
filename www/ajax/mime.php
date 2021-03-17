<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

define('MIME_URL_PREFIX', WEB_root . '/static/images/mime');
define('DEFAULT_FOLDER_ICON', MIME_URL_PREFIX . '/application-document.svg');
define('DEFAULT_NONE_ICON', MIME_URL_PREFIX . '/none.svg');
define('MAX_AGE', 2419200); // 28 days

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

    /* Folders have a specific icon
     */
    if ($this->arg === ['folder']) {
        $filenames[] = DEFAULT_FOLDER_ICON;
    } else {

        /* Always need two args for the mime
         */
        if (\count($this->arg) !== 2) {
            throw new Schoolbox\Mapper\Exception('No mime specified');
        }

        /* Fall back to something that was specified in Allowed Files
         */
        /** @var Mimetype $mime */
        $mime = MimetypeMapper::getInstance()->findOneByColumnList([
            'mime' => strtolower(implode('/', $this->arg)),
            'enable' => 'true'
        ]);

        $filenames[] = MIME_URL_PREFIX . '/' . $mime->getIconFile();
    }
} catch (Schoolbox\Mapper\Exception $e) {

    /* Something died in MimetypeMapper, probably
     * noop
     */
}

/* A default icon, in case none were found elsewhere
 * Hopefully this always exists
 */
$filenames[] = DEFAULT_NONE_ICON;

/* Check each of the possible filenames for what we chose
 *
 * Start with the one associated by MimetypeMapper which is synonymous for Allowed Files configuration
 * then gradually fall back to other files
 */
foreach ($filenames as $filename) {

    /* Progressively degrade icon choice until we find the first available
     */
    if (!file_exists($filename)) {
        continue;
    }

    $fp = fopen($filename, 'rb');

    // send the right headers
    header('Content-Type: image/svg+xml');
    header('Content-Length: ' . filesize($filename));
    header('Pragma: public');
    header('Cache-Control: public, max-age=' . MAX_AGE);
    header('Expires: ' . date('r', $_SERVER['REQUEST_TIME'] + MAX_AGE));

    // dump the picture and stop the script
    fpassthru($fp);

    exit;
}

error(41);
