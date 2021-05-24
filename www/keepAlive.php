<?php

/**
 * Keeps the current user's session alive.
 *
 * This is intended as a ping from the survey page - a student might be staring
 * at an answer for a long time, and if their session times out it will be lost.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

// Won't work if not a sitepage
if (!isset($this)) {
    header('Location: /keepAlive');
    exit;
}

// Always JSON
header('Content-type: text/json');

$user = $_SESSION['user'];
if ($user instanceof Schoolbox\User) {
    echo json_encode(['id' => $user->getId(),]);
} else {
    // Something's wrong
    http_response_code(400);
    echo json_encode(['error' => 'Unauthenticated']);
}
exit;
