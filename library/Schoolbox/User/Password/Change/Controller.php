<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Password\Change;

use Alaress\Password\Service;
use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

class Controller extends Base
{

    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var Service
     */
    private $passwordService;


    /**
     * Controller constructor.
     *
     * @param UserMapper  $userMapper
     * @param Service     $passwordService
     */
    public function __construct(UserMapper $userMapper, Service $passwordService)
    {
        $this->userMapper = $userMapper;
        $this->passwordService = $passwordService;
    }


    /**
     * Provide form
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getChangePassword()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@passwordChange/index.twig', [
            'isRequestMade' => $this->getSessionSegment()->getFlash('isRequestMade'),
            'isPasswordReset' => $this->getSessionSegment()->getFlash('isPasswordReset'),
        ])->setTitle('Change Password'));
    }


    /**
     * Process the form vars
     *
     * @throws \PDOException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postChangePassword()
    {
        /* Flash message that will appear on the getPasswordChange screen
         */
        $this->getSessionSegment()->setFlash('isRequestMade', true);
        $this->getSessionSegment()->setFlash('isPasswordReset', false);

        $user = $this->getUser();

        $currentPassword = $this->getRequest()->post->get('current_password', '');
        $userPassword = $user->getPassword();
        $newPassword = $this->getRequest()->post->get('new_password', '');
        $confirmPassword = $this->getRequest()->post->get('confirm_password', '');

        /* To change a password:
         * - all specification criteria must be met
         * - it has to successfully save
         */
        $validChangeRequest = new ValidChangeRequest($this->passwordService);

        if ($validChangeRequest->isSatisfiedBy($currentPassword, $userPassword, $newPassword, $confirmPassword)) {
            $user->setPassword($this->passwordService->hash($newPassword));
            // this is required for syncing external password
            $user->setRetrievablePassword($newPassword);

            try {
                $this->userMapper->save($user);
                $this->getSessionSegment()->setFlash('isPasswordReset', true);
            } catch (\UnexpectedValueException | Exception $e) {
                // noop
            }
        }

        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('passwordChange.getChangePassword')
        );
    }
}
