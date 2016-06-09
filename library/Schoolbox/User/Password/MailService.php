<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Password;

use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\User;
use Swift_RfcComplianceException;

/**
 * Class MailHelper
 * @package Schoolbox\User\Password
 */
class MailService
{
    /**
     * @var IConfigLoader
     */
    protected $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Sends a change password confirmation email.
     *
     * @param User $user The user to send to.
     *
     * @return bool
     */
    public function sendChangePasswordEmail(User $user)
    {
        $result['EMAIL']     = $user->getEmail();
        $result['FULLNAME']  = $user->formatName();
        $result['FIRSTNAME'] = $user->getFirstname();
        $result['DATE']      = date('j F Y', $_SERVER['REQUEST_TIME']);
        $result['TIME']      = date('g:i a', $_SERVER['REQUEST_TIME']);

        $core  = new CoreContext($this->config);
        $email = new TwigEmail('passwordchanged.twig');
        $email->setCoreContext($core->getContext());
        try {
            $email->setFrom($this->config->get('password_change_email_from'), $this->config->get('mail_from_name'));
            $email->setSubject($this->config->get('title') . ' Password Changed');
            $context['recipient'] = $result['FIRSTNAME'];
            $context['date'] = $result['DATE'];
            $context['time'] = $result['TIME'];
            return $email->setContext($context)->send(array(
                'name' => $result['FULLNAME'],
                'email' => $result['EMAIL']
            ));
        } catch (Swift_RfcComplianceException $e) {
            return false;
        }
    }

    /**
     * Sends a password change fail message to the Schoolbox admin, if there is
     * one.
     *
     * @param User $user The user to send to.
     *
     * @return bool
     */
    public function sendPasswordChangeFailureEmail(User $user)
    {
        $admin_address = $this->config->get('mail_admin_address');
        if (empty($admin_address)) {
            return false;
        }
        $template = 'change-password-failure.html';
        $subject  = $this->config->get('password_change_failed_email_subject');
        $mailHandler = new \Mailer($template, $subject, $admin_address);
        $mailHandler->setVar('USERNAME', $user->getUsername());
        $mailHandler->setVar('NAME', $user->formatName());
        $mailHandler->setVar('EMAIL', $user->getEmail());
        $mailHandler->setVar('IP', $_SERVER['REMOTE_ADDR']);
        $mailHandler->setVar('DATE', date('j F Y', $_SERVER['REQUEST_TIME']));
        $mailHandler->setVar('TIME', date('g:i a', $_SERVER['REQUEST_TIME']));
        return $mailHandler->send();
    }
}
