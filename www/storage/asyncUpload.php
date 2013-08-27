<?php
/**
 * Asynchronous upload of a brownhole file.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

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

$result = array(
    'success' => false,
    'error'   => 500,
    'message' => 'Unknown Error',
    'hash'    => '',
);
if (!$_SESSION['id']) {
    $result['message'] = 'Unauthorized';
    $result['error']   = 403;
}
elseif (is_readable($_FILES['upload']['tmp_name'])) {
    try {
        $imageResource = Schoolbox\FileMapper::getInstance()->upload(
            $_FILES['upload']['name'], $_FILES['upload']['tmp_name'], $_SESSION['id']
        );
        $result['success'] = true;
        $result['message'] = 'OK';
        $result['error']   = 200;
        $result['hash']    = $imageResource->getHash();
    }
    catch (Exception $ex) {
    }
}

echo json_encode($result);
