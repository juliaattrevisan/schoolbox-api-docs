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

    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
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
        $email          = $this->getRequest()->post->get('email');
        $sessionSegment = $this->getSession()->getSegment(static::class);
        $sessionSegment->setFlash('email', $email);
        $sessionSegment->setFlash('isEmailSubmitted', true);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $sessionSegment->setFlash('isEmailValid', false);
        } else {
            $sessionSegment->setFlash('isEmailValid', true);
            // Find all users with this email address. (not counting email_address_from_username)
            // If there is one, send a reset email to this address.
            foreach ($this->userMapper->findByColumn('alt_email', $email) as $user) {
                // Make a password reset attempt (for each account, I guess)
                // And send it
            }
            // No matches - send a message anyway?
        }
        $this->getResponse()->redirect->to($this->getRouter()->generate('user.passwordReset'));
    }
}
