<?php
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\StorageMapper;

/**
 * Asynchronous upload of a brownhole file.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$returnMeta = false;

// Force session usage due to flash uploader
// http://www.uploadify.com/documentation/uploadify/using-sessions-with-uploadify/
if (isset($_POST[session_name()])) {
    session_id($_POST[session_name()]);
    session_start();
}
if (isset($_GET[session_name()])) {
    session_id($_GET[session_name()]);
    session_start();
}
if (isset($_POST['fallback']) && isset($_COOKIE[session_name()])) {
    session_start();
    $returnMeta = true;
}

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
                $result = attemptStorage(getFileAtIndex($meta, $i), $returnMeta);
                $hashes[] = $result['hash'];
                if ($returnMeta) {
                    $metas[] = $result['meta'];
                }

            } catch (Exception $e) {
                $hashes[] = 'exception:'.$e->getMessage();
                if ($returnMeta) {
                    $metas[] = array();
                }
            }
        }
        $result['hash'] = $hashes;
        if ($returnMeta) {
            $result['meta'] = $metas;
        }

    } else {
        try {
            $result = attemptStorage($meta, $returnMeta);
        } catch (Exception $e) {
            $result['hash'] = 'exception:'.$e->getMessage();
            if ($returnMeta) {
                $result['meta'] = array();
            }
        }
    }
}

header(':', true, $result['error']);
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
 * @param bool $returnMeta
 * @throws Schoolbox\File\Exception
 * @return array
 */
function attemptStorage($fileData, $returnMeta)
{
    if (!is_readable($fileData['tmp_name'])) {
        throw new Schoolbox\File\Exception("Filedata not readable");
    }

    $storage = StorageMapper::getInstance()->upload($fileData['name'], $fileData['tmp_name'], $_SESSION['id']);

    $result = array(
        'success' => true,
        'error'   => 200,
        'message' => 'OK',
        'hash'    => NodeFactory::STORE . ':' . $storage->getHash(),
    );

    if ($returnMeta) {
        $result['meta'] = array(
            'name' => $storage->getFilename(),
            'type' => $storage->getMime(),
            'size' => $storage->getFilesize()
        );
    }

    return $result;
}
