<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\UserMapper;


class Controller extends Base
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var Password\Reset\Mapper
     */
    protected $resetMapper;

    /**
     * Constructor
     *
     * @param UserMapper            $userMapper
     * @param Password\Reset\Mapper $resetMapper
     */
    public function __construct(UserMapper $userMapper, Password\Reset\Mapper $resetMapper)
    {
        $this->userMapper  = $userMapper;
        $this->resetMapper = $resetMapper;
    }

    /**
     * Function to transfer to another user (impersonate), stores your previous session for later return
     *
     * @param User $user
     */
    public function impersonate(User $user) {
        $previous = $_SESSION;
        $session = new \Session();

        if ($session->create($user->getUsername())) {
            $_SESSION['previous'] = $previous;

            $logger = new \User_Logger_AssumeUser($previous['user'], $user);
            $logger->notify();
        }
        else {
            $_SESSION = $previous; //Recover old session
        }
        $this->getResponse()->redirect->found('/');
    }

    /**
     * Return to your previous session, after imper
     */
    public function recoverSession() {
        $_SESSION = $_SESSION['previous'];
        $this->getResponse()->redirect->found('/');
    }

    /**
     * Password reset request action
     */
    public function passwordReset()
    {
        if ($this->getUser()->getId()) {
            // You are logged in. This page is not for you
            $this->getResponse()->redirect->found('/');
        } else {
            $sessionSegment = $this->getSession()->getSegment(static::class);
            $this->getResponse()->content->set([
                'template' => '@user/passwordReset.twig',
                'data'     => [
                    'email'            => $sessionSegment->getFlash('email', ''),
                    'isEmailValid'     => $sessionSegment->getFlash('isEmailValid', true),
                    'isEmailSubmitted' => $sessionSegment->getFlash('isEmailSubmitted', false),
                ],
            ]);
        }
    }

    /**
     * Process password reset request action
     */
    public function requestPasswordReset()
    {
        $emailAddress   = $this->getRequest()->post->get('email');
        $sessionSegment = $this->getSession()->getSegment(static::class);
        $sessionSegment->setFlash('email', $emailAddress);
        $sessionSegment->setFlash('isEmailSubmitted', true);
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $sessionSegment->setFlash('isEmailValid', false);
        } else {
            $sessionSegment->setFlash('isEmailValid', true);
            // Find all users with this email address. (not counting email_address_from_username)
            // If there is one, send a reset email to this address.
            /** @var User[] $users */
            $users = $this->userMapper->findByColumn('alt_email', $emailAddress);
            if (count($users) > 0) {
                foreach ($users as $user) {
                    // Make a password reset attempt (for each account, I guess)
                    // And send it
                    $reset = $this->resetMapper->createModel();
                    if ($user->getEnabled()) {
                        $reset->setUser($user);
                        $this->resetMapper->save($reset);
                    }

                    // TODO refactor all of this
                    $myconf = \Schoolbox\Config\ConfigMapper::getInstance();
                    $core   = new \Schoolbox\Mail\CoreContext($myconf);
                    $email  = new \Schoolbox\Mail\TwigEmail('resetPassword.twig');
                    $email->setCoreContext($core->getContext());
                    $email->setFrom($myconf->get('password_change_email_from'), $myconf->get('mail_from_name'));
                    $email->setSubject($myconf->get('title') . ' Password Reset');
                    $email
                        ->setContext(['user' => $user, 'reset' => $reset,])
                        ->send(['name' => $user->formatName(), 'email' => $emailAddress,]);
                }
            } else {
                // No matches - send a message anyway?
                // Weigh up security (give away no information about whether account exists)
                // vs. flood prevention (don't send too many emails)
                // vs. convenience (maybe user got password wrong)
                // TODO refactor all of this too
                $myconf = \Schoolbox\Config\ConfigMapper::getInstance();
                $core   = new \Schoolbox\Mail\CoreContext($myconf);
                $email  = new \Schoolbox\Mail\TwigEmail('resetPasswordUserNotExists.twig');
                $email->setCoreContext($core->getContext());
                $email->setFrom($myconf->get('password_change_email_from'), $myconf->get('mail_from_name'));
                $email->setSubject($myconf->get('title') . ' Password Reset');
                $email->send($emailAddress);
            }
        }
        $this->getResponse()->redirect->to($this->getRouter()->generate('user.passwordReset'));
    }
}
