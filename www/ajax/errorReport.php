<?php
/**
 * Receives an AJAX request to forward an error report to Alaress
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// require sitepage context
if(!isset($this)) {
    die();
}

if(!count($_POST['err'])) {
    die();
}

// where to send the error report
$from = 'trouble@' . Config::get('site_domain');
$to = 'support@alaress.com.au';

// combine all the articles together, and sub the H1s for H2s
$errString = str_replace('h1>','h2>', implode("\n", $_POST['err']));

$email = new Mailer('errorReport.html', 'Giza Error Report', $to);
$email->setFrom($from, 'Giza Error Reporting');

$email->setVar(array(
    'ERR_CONTENTS' => $errString
));

$mailResponse = $email->send();

if($mailResponse instanceof PEAR_Error) {
    // mailfail
    // log the report to file instead
    $dir = Config::get('logs_dir');
    $fp = fopen($dir . '/giza'.date('YmdHis').'.err', 'w');
    if(!$fp) {
        die('Could not log the report, sorry. If the problem persists please contact your System Administrator.');
    }
    fwrite($fp, $errString);
    fclose($fp);
    die('Report logged. Thankyou!');
}

die('Report sent. Thankyou!');
