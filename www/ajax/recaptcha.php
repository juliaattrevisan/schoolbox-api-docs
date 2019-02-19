<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

if (isset($_GET['recaptcha_answer'])) {
	if (ReCaptcha::checkResponse($_GET['recaptcha_answer'], $_GET['recaptcha_challenge']))
		$output = array('verified'	=> 'true');
	else
		$output = array('verified'	=> 'false');

} else {
	/* get new challenge */
	$output = array('challenge' => ReCaptcha::getChallenge());
}

header("Content-type: application/json");
print json_encode($output);

?>
