<?php

use Zend\Uri\Uri;

/** @var Schoolbox\User $user */
$user = $_SESSION["user"];

// The PTO destination URL
$remoteUrl = "https://bookings.parentteacheronline.com.au?school=[school]&Type=[type]&P1=[username]&P2=[epoch]&P3=[hash]";

$keys['epoch'] = date("U");
$keys['school'] = Config::get('pto_school');
$keys['username'] = $_SESSION["user"]->getUsername();
$keys['type'] =                                                                         // Try to work out user type
    $user->getRole()->isStudent() || $user->getRole()->isParent() ? 'S' :               // Student/Parent
        $user->getRole()->isStaff() ? 'T' :                                             // Teacher
            $user->isSuperuser() ? 'A' :                                                // Administrator
                '*';                                                                    // Leave it up to PTO to work out
$keys['hash'] = hash('sha256', $keys['school'] . $keys['username'] . $keys['epoch'] . Config::get('pto_key'));

// Replace placeholders in PTO url
foreach ($keys as $key => $value) {
    $remoteUrl = str_replace("[{$key}]", $value, $remoteUrl);
}

// Ensure URL is valid
$url = new Uri($remoteUrl);
if (!$url->isValid()) {
    $this->error(2);
}

// Whack it in a pretty frame
$this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
$this->setVar(array(
    'REMOTE_URL' => addslashes($url->toString()),
    'FRAME_HEIGHT' => $height,
));
