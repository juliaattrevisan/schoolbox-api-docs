<?php

/**
 * Asynchronous upload of a brownhole file.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Translator\ModelToArray;

// disallow SitePage context, only direct access is allowed
if (isset($this)) {
    http_response_code(404);
    die('Only direct access permitted');
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/** @var PrefixGenerator $prefixGenerator */
$prefixGenerator = $kernel->get('cdnPrefixGenerator');

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

    /* Optional expiry timestamp
     */
    $expiryUnixTimestamp = $_GET['expiry'] ?? null;
    $expiryAt = null;

    if (null !== $expiryUnixTimestamp) {
        $expiryAt = \Alaress_Date_Format::newFromTimestamp($expiryUnixTimestamp);
    }

    if (is_array($meta['name'])) {
        $hashes = array();
        $metas = array();
        for ($i = 0; $i < count($meta['name']); ++$i) {
            try {
                $result = attemptStorage(getFileAtIndex($meta, $i), $prefixGenerator, $expiryAt);
                $hashes[] = $result['hash'];
                $metas[]  = $result['meta'];
            } catch (Exception $e) {
                $hashes[] = 'exception:' . $e->getMessage();
                $metas[]  = array();
            }
        }
        $result['hash'] = $hashes;
        $result['meta'] = $metas;
    } else {
        try {
            $result = attemptStorage($meta, $prefixGenerator, $expiryAt);
        } catch (Exception $e) {
            $result['hash'] = 'exception:' . $e->getMessage();
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
    foreach (array_keys($meta) as $field) {
        $rtn[$field] = $meta[$field][$i];
    }
    return $rtn;
}

/**
 * @param array                    $fileData
 * @param PrefixGenerator          $prefixGenerator
 * @param Alaress_Date_Format|null $expiryAt
 * @return array
 * @throws PDOException
 * @throws UnexpectedValueException
 * @throws \League\Flysystem\FileNotFoundException
 * @throws \Schoolbox\File\Exception
 * @throws \Schoolbox\Mapper\DefinitionException
 * @throws \Schoolbox\Mapper\Exception
 */
function attemptStorage(array $fileData, PrefixGenerator $prefixGenerator, ?\Alaress_Date_Format $expiryAt = null): array
{
    if ($fileData['size'] == 0) {
        throw new Schoolbox\File\Exception("File has no content");
    }
    if (!is_readable($fileData['tmp_name'])) {
        throw new Schoolbox\File\Exception("Filedata not readable");
    }

    $storage = StorageMapper::getInstance()->accept($fileData['name'], $fileData['tmp_name'], $expiryAt);

    // to also return the stored file location for possible reference / display
    $modelToArray = new ModelToArray($prefixGenerator);

    return [
        'success' => true,
        'error'   => 200,
        'message' => 'OK',
        'hash'    => NodeFactory::STORE . ':' . $storage->getHash(),
        'meta'    => [
            'type' => $storage->getMimetype()->getMime(),
            'size' => $storage->getSize(),
            'name' => $fileData['name'],
            'file' => $modelToArray($storage)
        ]
    ];
}
