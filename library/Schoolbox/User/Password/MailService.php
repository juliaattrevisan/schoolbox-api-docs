<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password;

use PDOException;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

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
    public function __construct(
        IConfigLoader $config,
        Reset\Mapper $resetMapper,
        UserMapper $userMapper,
        TwigEmailFactory $mailFactory
    ) {
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
            $users = $users->mergeUnique($this->userMapper->findByEmail($emailAddress), 'id');
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
        } catch (TransportExceptionInterface $e) {
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
    private function sendResetToUsers(
        array $users,
        string $remoteAddress,
        string $canResetTemplate,
        string $canNotResetTemplate,
        ?string $subject = null
    ) {
        $sent = [];
        $subject     = $subject ?: $this->config->get('title') . ' Password Reset';

        if (empty($users)) {
            // No users to mail to were found.
            // To mitigate timing attacks: craft the email regardless, and send
            // it to the blackhole.
            $email = $this->mailFactory->build('userPasswordReset/userNotExists.twig', $subject, '');
            $email->sendToBlackhole();
            return $sent;
        }

        $fromAddress = $this->config->get('password_change_email_from');
        $fromName    = $this->config->get('mail_from_name');

        foreach ($users as $user) {
            // Make sure the user is allowed to reset password based on their role
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
                    $this->resetMapper->createModel()->setUser($user),
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
        $user = $reset->getUser();
        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $email = $this->mailFactory->build('userPasswordReset/userNotExists.twig', $subject, '');
            $email->sendToBlackhole();
            return true;
        }

        $email = $this->mailFactory->build($template, $subject, new Address($user->getEmail(), $user->formatName()));
        $email->setFrom($fromAddress, $fromName);
        $email->encodeBase64();
        $email->setContext([
                               'user'            => $user,
                               'reset'           => $reset,
                               'hideUnsubscribe' => true,
                           ]);
        try {
            // Only try and send the email if we got a valid address in the end
            return $email->send();
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }
}
