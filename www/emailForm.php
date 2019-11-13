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

use Alaress\Mail\AbstractMailer;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

session_start();

$recaptcha = new ReCaptcha(Config::get('recaptcha_public_api_key'), Config::get('recaptcha_private_api_key'));

if (!isset($_SESSION['id']) && !$recaptcha->verify($_POST['g-recaptcha-response'])) {
    $page = new SitePage(false);
    error(105, "The Human Checker (reCAPTCHA) failed.");
}

if(isset($_SESSION['user'])) {
    /** @var \Schoolbox\User $user */
    $user = $_SESSION['user'];
    $name = $user->getFullName();
} else {
    $name = $_POST['name'];
}

if (validateEmailAddress($_POST['email']) && preg_match("/^[a-zA-Z0-9\/\-\_]+\.html$/", $_POST['template'])) {
    foreach ($_POST as $key => $var) {
        if (!checkSpamHeaders($var)) {
            SitePage::localRedir('/');
        }

        $key = strtoupper($key);
        if (is_array($var)) {
            $context[$key] = implode(AbstractMailer::implodeGlue(), $var);
        } else {
            $context[$key] = $var;
        }
    }

    $context['NAME'] = $name;

    $myconf = ConfigLoader::getInstance();
    $core   = new CoreContext($myconf);
    $email  = new TwigEmail('feedback.twig');
    $email->setCoreContext($core->getContext());
    $email->setFrom($_POST['email'], $name);
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

if (preg_match("/^[a-zA-Z0-9\/\-\_]+/", $_POST['confirmation'])) {
    SitePage::localRedir($_POST['confirmation']);
} else {
    SitePage::localRedir('/feedback/thank-you');
}

function checkSpamHeaders($text)
{
    //checks for common spammer injected headers
    if (stristr($text, 'Content-Type:') !== false) {
        return false;
    }
    if (stristr($text, 'MIME-Version:') !== false) {
        return false;
    }
    if (stristr($text, 'Bcc:') !== false) {
        return false;
    }

    return true;
}


function validateEmailAddress($email)
{
    //check from contains valid email
    if (!preg_match('/^([0-9a-zA-Z]+[-._+&])*[0-9a-zA-Z]+@([-0-9a-zA-Z]+[.])+[a-zA-Z]{2,6}$/', $email)) {
        return false;
    }

    //do domain lookup
    list($alias, $domain) = explode('@', $email);

    //this prevents appending of localhost to lookup
    $domain = $domain.'.';

    //check for mx records or that it resolves
    if (getmxrr($domain, $temp)) {
        return true;
    } elseif (checkdnsrr($domain, 'ANY')) {
        return true;
    } else {
        return false;
    }
}
