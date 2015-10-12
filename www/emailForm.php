<?php

/* This form used to be for generic emails, now only used for the feedback form
   It requires the following variables;
			template - template filename to be used
			email - email address of sender
   The following are optional but recommended variables
			subject - subject of email to be sent
			name - name of sender
			confirmation - location to redirect user to after submission, ie a confirmation page
*/

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

session_start();

$recaptcha = new ReCaptcha( Config::get('recaptcha_public_api_key'), Config::get('recaptcha_private_api_key') );

if ( !isset($_SESSION['id']) && !$recaptcha->check_answer($_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']) ) {
    $page = new SitePage( false );
    error(105,"The Human Checker (reCAPTCHA) words you entered did not match.");
}

if(validateEmailAddress($_POST['email']) && preg_match("/^[a-zA-Z0-9\/\-\_]+\.html$/", $_POST['template'])) {

	foreach($_POST as $key => $var) {
		if(!checkSpamHeaders($var))
			SitePage::localRedir('/');

		$key = strtoupper($key);
		if(is_array($var))
			$context[$key] = implode(', ',$var);
		else
			$context[$key] = $var;
	}

        $myconf = ConfigMapper::getInstance();
        $core = new CoreContext($myconf);
        $email = new TwigEmail('feedback.twig');
        $email->setCoreContext($core->getContext());
        $email->setFrom($_POST['email'], $_POST['name']);
        $email->setSubject(Cfg::get('title') . ' Feedback - ' . $_POST['subject']);
        $email->setContext($context)->send(array(
                            'name' => Cfg::get('mail_from_name'),
                            'email' => Cfg::get('mail_support_address')
                    ));

/*	if($_POST['receipt']) {
        $receiptHandler = new Mailer($_POST['receipt'], $_POST['receipt_subject'], $_POST['email']);
		$receiptHandler->setVar($output);
		$receiptHandler->setVar('SITE_DOMAIN', $_SERVER['HTTP_HOST']);
		$receiptHandler->send();
	}
*/
}

if(preg_match("/^[a-zA-Z0-9\/\-\_]+/", $_POST['confirmation']) ) {
	SitePage::localRedir($_POST['confirmation']);
}
else {
	SitePage::localRedir('/thank-you');
}

function checkSpamHeaders($text)
{
	//checks for common spammer injected headers
	if(stristr($text, 'Content-Type:') !== FALSE)
		return FALSE;
	if(stristr($text, 'MIME-Version:') !== FALSE)
		return FALSE;
	if(stristr($text, 'Bcc:') !== FALSE)
		return FALSE;

	return TRUE;
}


function validateEmailAddress($email)
{
	//check from contains valid email
	if(!preg_match('/^([0-9a-zA-Z]+[-._+&])*[0-9a-zA-Z]+@([-0-9a-zA-Z]+[.])+[a-zA-Z]{2,6}$/', $email))
		return FALSE;

	//do domain lookup
	list($alias, $domain) = explode('@', $email);

	//this prevents appending of localhost to lookup
	$domain = $domain.'.';

	//check for mx records or that it resolves
	if(getmxrr($domain, $temp))
		return TRUE;
	else if(checkdnsrr($domain, 'ANY') )
		return TRUE;
	else
		return FALSE;
}
?>
