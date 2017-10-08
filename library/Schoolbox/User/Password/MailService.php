<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Password;

use PDOException;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Swift_RfcComplianceException;
use Swift_SwiftException;


/**
 * Class MailService
 *
 * This is a domain class that provides high-level abstraction for sending password emails in a variety of contexts.
 *
 *
 * @package Schoolbox\User\Password
 */
class MailService
{
    /**
     * @var IConfigLoader
     */
    protected $config;

    /**
     * @var TwigEmailFactory
     */
    private $mailFactory;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var Reset\Mapper
     */
    private $resetMapper;


    /**
     * @param IConfigLoader    $config
     * @param Reset\Mapper     $resetMapper
     * @param UserMapper       $userMapper
     * @param TwigEmailFactory $mailFactory
     */
    public function __construct(IConfigLoader $config, Reset\Mapper $resetMapper, UserMapper $userMapper, TwigEmailFactory $mailFactory)
    {
        $this->config = $config;
        $this->mailFactory = $mailFactory;
        $this->userMapper = $userMapper;
        $this->resetMapper = $resetMapper;
    }


    /**
     * If registered to an account, send an email to the given address containing a
     * link to reset the password of the corresponding account.
     *
     * @param string $emailAddress
     * @param string $remoteAddress
     * @throws PDOException if the attempt to save the Reset object fails
     * @return void
     */
    public function sendResetLinkToAddress($emailAddress, $remoteAddress)
    {
        $users = $this->userMapper->findByColumn('username', $emailAddress);

        // If the user entered a validly formatted email address- search by email address as well

        if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $users = $users->merge($this->userMapper->findByEmail($emailAddress));
        }

        $this->sendResetToUsers(
            $users->getAll(),
            $remoteAddress,
            'userPasswordReset/reset.twig',
            'userPasswordReset/userNotAllowedReset.twig'
        );
    }


    /**
     * Send an email to the address of the given user containing a welcome message
     * and a link to set their password for the first time.
     *
     * @param User $user
     * @throws PDOException if the attempt to save the Reset object fails
     * @return void
     */
    public function sendWelcomeEmailToUser(User $user)
    {
        $this->sendResetToUsers(
            [ $user ],
            '0.0.0.0',
            'userPasswordReset/welcome/reset.twig',
            'userPasswordReset/welcome/userNotAllowedReset.twig',
            'Welcome to ' . $this->config->get('title')
        );
    }


    /**
     * @param User   $user
     * @param string $oldUsername
     * @retur bool                whether or not the email successfully sent
     */
    public function sendUsernameChangeEmailToUser(User $user, $oldUsername)
    {
        $subject     = $this->config->get('title') . ' Username Change';
        $fromAddress = $this->config->get('password_change_email_from');
        $fromName    = $this->config->get('mail_from_name');

        $email = $this->mailFactory->build(
            'userPasswordReset/welcome/usernameChange.twig',
            $subject
        );

        $email->setFrom($fromAddress, $fromName);

        $email->setContext([
            'user'        => $user,
            'oldUsername' => $oldUsername
        ]);

        try {
            return $email->send([
                'name'  => $user->formatName(),
                'email' => $user->getEmail()
            ]);
        } catch (Swift_SwiftException $e) {
            return false;
        }
    }

    /**
     * Send an email to the list of users given with one of the given templates (depending on whether that
     * user is or isn't allowed to change their email address)
     *
     * @param User[] $users
     * @param string $remoteAddress
     * @param string $canResetTemplate
     * @param string $canNotResetTemplate
     * @param string $subject
     * @throws PDOException                 if the attempt to save the Reset object fails
     * @return bool[]                       array of successes/failures, indexed by address, ie. [ email => success ]
     */
    private function sendResetToUsers(array $users, $remoteAddress, $canResetTemplate, $canNotResetTemplate, $subject = null)
    {
        $sent = [];

        if (empty($users)) {
            // bai.
            return $sent;
        }

        $subject     = $subject ?: $this->config->get('title') . ' Password Reset';
        $fromAddress = $this->config->get('password_change_email_from');
        $fromName    = $this->config->get('mail_from_name');

        foreach ($users as $user) {
            // Make sure the user is allowed to reset password based on their role
            $reset = null;

            if ($user->getRole()->getCanChangePassword()) {
                $reset = $this->resetMapper->createModel()->setIp($remoteAddress);

                if ($user->getEnabled()) {
                    $reset->setUser($user);
                    $this->resetMapper->save($reset);

                    $sent[$user->getEmail()] = $this->sendResetWithTemplate(
                        $reset,
                        $canResetTemplate,
                        $subject,
                        $fromAddress,
                        $fromName
                    );
                }
            } else {
                // Password reset not allowed
                $sent[$user->getEmail()] = $this->sendResetWithTemplate(
                    $reset,
                    $canNotResetTemplate,
                    $subject,
                    $fromAddress,
                    $fromName
                );
            }
        }

        return $sent;
    }


    /**
     * @param Reset\Model $reset
     * @param string      $template
     * @param string      $subject
     * @param string      $fromAddress
     * @param string      $fromName
     * @return bool                    whether or not the email successfully sent
     */
    private function sendResetWithTemplate(Reset\Model $reset, $template, $subject, $fromAddress, $fromName)
    {
        $user  = $reset->getUser();
        $email = $this->mailFactory->build($template, $subject);

        $email->setFrom($fromAddress, $fromName);

        try {
            if (filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
                // Only try and send the email if we got a valid address in the end
                $email->setContext([
                    'user'            => $user,
                    'reset'           => $reset,
                    'hideUnsubscribe' => true
                ]);

                return $email->send([
                    'name'  => $user->formatName(),
                    'email' => $user->getEmail()
                ]);
            }

            // Not a valid email address, send blackhole email
            $email = $this->mailFactory->build('userPasswordReset/userNotExists.twig');
            $email->sendToBlackhole();
            // technically this email "sent"
            return true;

        } catch (Swift_SwiftException $e) {
            return false;
        }
    }


    /**
     * Sends a change password confirmation email.
     *
     * @deprecated this method is only called from /www/account/modifyForm.php, which itself is not used
     *
     * @param User $user The user to send to.
     *
     * @return bool
     */
    public function sendChangePasswordEmail(User $user)
    {
        $core  = new CoreContext($this->config);
        $email = new TwigEmail('passwordchanged.twig');
        $email->setCoreContext($core->getContext());
        try {
            $email->setFrom($this->config->get('password_change_email_from'), $this->config->get('mail_from_name'));
            $email->setSubject($this->config->get('title') . ' Password Changed');
            return $email
                ->setContext([
                    'recipient' => $user->getFirstname(),
                    'date'      => date('j F Y', $_SERVER['REQUEST_TIME']),
                    'time'      => date('g:i a', $_SERVER['REQUEST_TIME']),
                ])
                ->send([
                    'name'  => $user->formatName(),
                    'email' => $user->getEmail(),
                ]);
        } catch (Swift_RfcComplianceException $e) {
            return false;
        }
    }

    /**
     * Sends a password change fail message to the Schoolbox admin, if there is
     * one.
     *
     * @deprecated this method is only called from /www/account/modifyForm.php, which itself is not used
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
