<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress\Password\Service as PasswordService;
use Alaress\Web\Controller\Base;
use Schoolbox\User\Password\MailService;
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
     * @var MailService
     */
    protected $passwordMailService;

    /**
     * @var PasswordService
     */
    protected $passwordService;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     * @param Mapper $resetMapper
     * @param MailService $passwordMailService
     * @param PasswordService $passwordService
     */
    public function __construct(
        UserMapper $userMapper,
        Mapper $resetMapper,
        MailService $passwordMailService,
        PasswordService $passwordService
    ) {
        $this->userMapper = $userMapper;
        $this->resetMapper = $resetMapper;
        $this->passwordMailService = $passwordMailService;
        $this->passwordService = $passwordService;
    }

    /**
     * Action for displaying the form for creating a reset request.
     */
    public function displayCreateRequestForm()
    {
        $this->validateAccess();
        $sessionSegment = $this->getSessionSegment();
        $this->response = $this->responseFromTemplate(
            '@userPasswordReset/createRequestForm.twig',
            [
                'email' => $sessionSegment->getFlash('email', ''),
                'isEmailValid' => $sessionSegment->getFlash('isEmailValid', true),
                'isEmailSubmitted' => $sessionSegment->getFlash('isEmailSubmitted', false),
                'isResetExpired' => $sessionSegment->getFlash('isResetExpired', false),
            ],
            'Reset Password',
        );
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
        $this->response = $this->responseFromTemplate(
            '@userPasswordReset/resetForm.twig',
            [
                'reset' => $reset,
                'isPasswordSubmitted' => $sessionSegment->getFlash('isPasswordSubmitted', false),
                'isPasswordValid' => $sessionSegment->getFlash('isPasswordValid', true),
             ],
            'Reset Password',
        );
    }

    /**
     * Action for displaying a successful reset message.
     */
    public function displaySuccessMessage()
    {
        $this->response = $this->responseFromTemplate(
            '@userPasswordReset/successMessage.twig',
            [
                'isCommunityPortalPasswordSet' => $this->getSessionSegment()->getFlash('isCommunityPortalPasswordSet', true),
            ],
            'Password Reset Successful',
        );
    }

    /**
     * Action for creating a password reset request.
     * @throws \PDOException if the attempt to save the requisite Reset object fails
     */
    public function createRequest()
    {
        $this->validateAccess();
        $emailAddress = $this->requestPost->get('email');
        $sessionSegment = $this->getSessionSegment();
        $sessionSegment->setFlash('isEmailSubmitted', true);

        if (strlen($emailAddress) > 0) {
            $sessionSegment->setFlash('email', $emailAddress);

            $this->passwordMailService->sendResetLinkToAddress(
                $emailAddress,
                $this->request->getServerParams()['REMOTE_ADDR'] ?? ''
            );
        } else {
            $sessionSegment->setFlash('isEmailValid', false);
        }

        $this->response = $this->redirectFound(
            $this->routeGenerator->generate('userPasswordReset.displayCreateRequestForm')
        );
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
        $password = $this->requestPost->get('new_password', '');
        $confirm = $this->requestPost->get('confirm_password', '');

        // Verify that passwords match format and each other
        if ($reset->getUser() && $password === $confirm && mb_strlen($password, 'UTF-8') >= 8) {
            // Reset password
            // Refetch user too cause other things may break
            $user = $this->userMapper->findById($reset->getUser()->getId());
            $user
                ->setPassword($this->passwordService->hash($password))
                ->setRetrievablePassword($password);

            try {
                $this->userMapper->save($user);
            } catch (\UnexpectedValueException $e) {
                // Community portal integration was misconfigured
                $this->getSessionSegment()->setFlash('isCommunityPortalPasswordSet', false);
            }

            // This reset has been used, save it
            $this->resetMapper->save($reset->setResetTime(\Alaress_Date_Format::now()));

            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
                    'userPasswordReset.displaySuccessMessage'
                )
            );
        } else {
            $this->getSessionSegment()->setFlash('isPasswordValid', false);
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
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
            $this->response = $this->redirectFound('/');
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
        if ($reset->getUser() === null) {
            // User does not exist
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('userPasswordReset.displayCreateRequestForm')
            );
        } elseif (!$reset->getToken()) {
            // Token wasn't found - go elsewhere
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('userPasswordReset.displayCreateRequestForm')
            );
        } elseif ($reset->getResetTime()) {
            // Token already used - go elsewhere
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('userPasswordReset.displayCreateRequestForm')
            );
        } elseif ($reset->getExpiry()->lt(\Alaress_Date_Format::now())) {
            // This request has expired. Create a new one
            // Not setting email - stale requests should not give out this information.
            $this->getSessionSegment()->setFlash('isResetExpired', true);
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('userPasswordReset.displayCreateRequestForm')
            );
        }
    }
}
