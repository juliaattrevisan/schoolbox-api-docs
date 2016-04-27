<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress\Password\Service as PasswordService;
use Alaress\Web\Controller\Base;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Controller for password resets
 *
 * @package Schoolbox\User\Password\Reset
 */
class Controller extends Base
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var Mapper
     */
    protected $resetMapper;

    /**
     * @var TwigEmailFactory
     */
    protected $mailFactory;

    /**
     * @var PasswordService
     */
    protected $passwordService;

    /**
     * Constructor
     *
     * @param UserMapper       $userMapper
     * @param Mapper           $resetMapper
     * @param TwigEmailFactory $mailFactory
     * @param PasswordService  $passwordService
     */
    public function __construct(
        UserMapper $userMapper,
        Mapper $resetMapper,
        TwigEmailFactory $mailFactory,
        PasswordService $passwordService
    ) {
        $this->userMapper      = $userMapper;
        $this->resetMapper     = $resetMapper;
        $this->mailFactory     = $mailFactory;
        $this->passwordService = $passwordService;
    }

    /**
     * Action for displaying the form for creating a reset request.
     */
    public function displayCreateRequestForm()
    {
        $this->validateAccess();
        $sessionSegment = $this->getSessionSegment();
        $this->getResponse()->content->set([
            'title'    => 'Reset Password',
            'template' => '@userPasswordReset/createRequestForm.twig',
            'data'     => [
                'email'            => $sessionSegment->getFlash('email', ''),
                'isEmailValid'     => $sessionSegment->getFlash('isEmailValid', true),
                'isEmailSubmitted' => $sessionSegment->getFlash('isEmailSubmitted', false),
                'isResetExpired'   => $sessionSegment->getFlash('isResetExpired', false),
            ],
        ]);
    }

    /**
     * Action for displaying the form for a password reset.
     *
     * @param Model $reset
     */
    public function displayResetForm(Model $reset)
    {
        $this->validateAccess();
        $this->validateResetRequest($reset);
        $sessionSegment = $this->getSessionSegment();
        $this->getResponse()->content->set([
            'title'    => 'Reset Password',
            'template' => '@userPasswordReset/resetForm.twig',
            'data'     => [
                'reset'               => $reset,
                'isPasswordSubmitted' => $sessionSegment->getFlash('isPasswordSubmitted', false),
                'isPasswordValid'     => $sessionSegment->getFlash('isPasswordValid', true),
            ],
        ]);
    }

    /**
     * Action for displaying a successful reset message.
     */
    public function displaySuccessMessage()
    {
        $this->getResponse()->content->set([
            'title'    => 'Password Reset Successful',
            'template' => '@userPasswordReset/successMessage.twig',
        ]);
    }

    /**
     * Action for creating a password reset request.
     */
    public function createRequest()
    {
        $this->validateAccess();
        $emailAddress   = $this->getRequest()->post->get('email');
        $sessionSegment = $this->getSessionSegment();
        $sessionSegment->setFlash('email', $emailAddress);
        $sessionSegment->setFlash('isEmailSubmitted', true);
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $sessionSegment->setFlash('isEmailValid', false);
        } else {
            $sessionSegment->setFlash('isEmailValid', true);
            // Find all users with this email address.
            // If there is one, send a reset email to this address.
            $users  = $this->userMapper->findByEmail($emailAddress);
            $config = $this->getConfigLoader();
            if (count($users) > 0) {
                foreach ($users as $user) {
                    // Tell me more about their role
                    $reset = null;
                    if ($user->getRole()->getCanChangePassword()) {
                        // Make a password reset attempt (for each account, I guess)
                        // And send it
                        $reset = $this->resetMapper->createModel()
                            ->setIp($this->getRequest()->server->get('REMOTE_ADDR'));
                        if ($user->getEnabled()) {
                            $reset->setUser($user);
                            $this->resetMapper->save($reset);
                        }
                        $email = $this->mailFactory->build('userPasswordReset/reset.twig');
                    } else {
                        $email = $this->mailFactory->build('userPasswordReset/userNotAllowedReset.twig');
                    }

                    $email->setFrom($config->get('password_change_email_from'), $config->get('mail_from_name'));
                    $email->setSubject($config->get('title') . ' Password Reset');
                    $email->setContext(['user' => $user, 'reset' => $reset,]);
                    $email->send(['name' => $user->formatName(), 'email' => $emailAddress,]);
                }
            } else {
                // No matches - send a message anyway?
                // Weigh up security (give away no information about whether account exists)
                // vs. flood prevention (don't send too many emails)
                // vs. convenience (maybe user got password wrong)
                // Currently, this sacrifices convenience for flood protection.
                $email = $this->mailFactory->build('userPasswordReset/userNotExists.twig');
                $email->setFrom($config->get('password_change_email_from'), $config->get('mail_from_name'));
                $email->setSubject($config->get('title') . ' Password Reset');
                $email->sendToBlackhole();
            }
        }
        $this->getResponse()->redirect->to($this->getRouter()->generate('userPasswordReset.displayCreateRequestForm'));
    }

    /**
     * Action for verifying a password reset.
     *
     * @param Model $reset
     */
    public function reset(Model $reset)
    {
        $this->validateAccess();
        $this->validateResetRequest($reset);
        $password = $this->getRequest()->post->get('new_password', '');
        $confirm  = $this->getRequest()->post->get('confirm_password', '');

        // Verify that passwords match format and each other
        if ($password === $confirm
            && preg_match('/[a-z]/i', $password)
            && preg_match('/\\d/i', $password)
            && preg_match('/[^a-z\\d]/i', $password)
        ) {
            // Reset password
            $this->userMapper->save(
                $reset->getUser()
                    ->setPassword($this->passwordService->hash($password))
                    ->setRetrievablePassword($password)
            );

            // This reset has been used, save it
            $this->resetMapper->save($reset->setResetTime(\Alaress_Date_Format::now()));

            $this->getResponse()->redirect->to(
                $this->getRouter()->generate(
                    'userPasswordReset.displaySuccessMessage'
                )
            );
        } else {
            $this->getSessionSegment()->setFlash('isPasswordValid', false);
            $this->getResponse()->redirect->to(
                $this->getRouter()->generate(
                    'userPasswordReset.displayResetForm',
                    ['token' => $reset->getToken(),]
                )
            );
        }
    }

    /**
     * Validates that you can access the reset module.
     *
     * If you're logged in, this page is not for you - redirect to dashboard.
     */
    protected function validateAccess()
    {
        if ($this->getUser()->getId()) {
            $this->getResponse()->redirect->found('/');
        }
    }

    /**
     * Validates that this request hasn't expired, and actually exists.
     * Redirect back to the create request form if it's not usable.
     *
     * @param Model $reset
     */
    protected function validateResetRequest(Model $reset)
    {
        if (!$reset->getToken()) {
            // Token wasn't found - go elsewhere
            $this->getResponse()->redirect->to($this->getRouter()->generate('userPasswordReset.displayCreateRequestForm'));
        } elseif (!$reset->getResetTime()->isNull()) {
            // Token already used - go elsewhere
            $this->getResponse()->redirect->to($this->getRouter()->generate('userPasswordReset.displayCreateRequestForm'));
        } elseif ($reset->getExpiry()->lt(\Alaress_Date_Format::now())) {
            // This request has expired. Create a new one
            // Not setting email - stale requests should not give out this information.
            $this->getSessionSegment()->setFlash('isResetExpired', true);
            $this->getResponse()->redirect->to($this->getRouter()->generate('userPasswordReset.displayCreateRequestForm'));
        }
    }
}
