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

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

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

http_response_code($result['error']);
header('Content-Type: application/json');

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
