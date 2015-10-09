<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage(false);

$page->setFile(TEMPLATE_body, 'common/forgotten-password.html');
$page->setTitle('Forgotten Password');
$page->setBlock(TEMPLATE_body, 'email_sent', 'email_sent_block');
$page->setBlock(TEMPLATE_body, 'not_found', 'not_found_block');
$page->setBlock(TEMPLATE_body, 'parents_only', 'parents_only_block');

$page->setVar('email_sent_block', '');
$page->setVar('not_found_block', '');
$page->setVar('parents_only_block', '');

if (isset($_POST['email'])) {
	
	$userHandler = new User;
	
	$result = $userHandler->sendPassword($_POST['email']);

	if (!is_array($result)) {
            $page->parse('not_found_block', 'not_found');
	}
	else if (array_key_exists('USERNAME',$result)) {
	    $myconf = ConfigMapper::getInstance();
            $core = new CoreContext($myconf);
	    $email = new TwigEmail('forgotpassword.twig');
	    $email->setCoreContext($core->getContext());
	    $email->setFrom(Cfg::get('password_change_email_from'), Cfg::get('mail_from_name'));
	    $email->setSubject(Cfg::get('title') . ' Password Reminder');
	    $context['recipient'] = $result['FIRSTNAME'];
	    $context['username'] = $result['USERNAME'];
	    $context['password'] = $result['PASSWORD'];
	    $email->setContext($context)->send(array(
                                'name' => $result['FULLNAME'],
                                'email' => $result['EMAIL']
			));

	    $page->parse('email_sent_block', 'email_sent');
	}
	else {
            $page->parse('not_found_block', 'not_found');
	}

}

$page->outputPage(true);

?>
