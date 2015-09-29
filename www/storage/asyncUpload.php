<?php
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Asynchronous upload of a brownhole file.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

// Force session usage due to flash audio recorder.
// (Uploadify will die, but this is still needed for flash audio upload.)
$session     = new Session();
$sessionName = session_name();
if (isset($_REQUEST[$sessionName]) && $session->validateSessionId($_REQUEST[$sessionName])) {
    session_id($_REQUEST[$sessionName]);
}

session_start();

$result = array(
    'success' => false,
    'error'   => 500,
    'message' => 'Unknown Error',
    'hash'    => '',
);
if (!$_SESSION['id']) {
    $result['message'] = 'Unauthorized';
    $result['error']   = 403;

} elseif (isset($_FILES['upload'])) {
    $meta = $_FILES['upload'];

    if (is_array($meta['name'])) {
        $hashes = array();
        $metas = array();
        for ($i = 0; $i < count($meta['name']); ++$i) {
            try {
                $result = attemptStorage(getFileAtIndex($meta, $i));
                $hashes[] = $result['hash'];
                $metas[]  = $result['meta'];

            } catch (Exception $e) {
                $hashes[] = 'exception:'.$e->getMessage();
                $metas[]  = array();
            }
        }
        $result['hash'] = $hashes;
        $result['meta'] = $metas;

    } else {
        try {
            $result = attemptStorage($meta);
        } catch (Exception $e) {
            $result['hash'] = 'exception:'.$e->getMessage();
            $result['meta'] = array();
        }
    }
}

header(':', true, $result['error']);

// F U IE9 - I really don't want to do this
// TODO create a browscap service, and inject it around places
try {

    $browscap                 = new \phpbrowscap\Browscap(SITE_root . '/tools/browscap');
    $browscap->doAutoUpdate   = false;
    $browscap->updateMethod   = 'local';
    $browscap->updateInterval = '31536000';
    if ($browscap->getBrowser()->Browser == 'IE' && $browscap->getBrowser()->MajorVer <= 9) {
        header('Content-Type: text/plain');
    } else {
        header('Content-Type: application/json');
    }

} catch (Exception $e) {
    // if in doubt, err on the side of recklessness
    header('Content-Type: application/json');
}
die(json_encode($result));


/**
 * @param array $meta
 * @param int $i
 * @return array
 */
function getFileAtIndex($meta, $i)
{
    $rtn = array();
    foreach(array_keys($meta) as $field) {
        $rtn[$field] = $meta[$field][$i];
    }
    return $rtn;
}


/**
 * @param array $fileData
 * @throws Schoolbox\File\Exception
 * @return array
 */
function attemptStorage($fileData)
{
    if ($fileData['size'] == 0) {
        throw new Schoolbox\File\Exception("File has no content");
    }
    if (!is_readable($fileData['tmp_name'])) {
        throw new Schoolbox\File\Exception("Filedata not readable");
    }

    $storage = StorageMapper::getInstance()->accept($fileData['name'], $fileData['tmp_name']);

    return [
        'success' => true,
        'error'   => 200,
        'message' => 'OK',
        'hash'    => NodeFactory::STORE . ':' . $storage->getHash(),
        'meta'    => [
            'type' => $storage->getMimetype()->getMime(),
            'size' => $storage->getSize(),
        ]
    ];
}
