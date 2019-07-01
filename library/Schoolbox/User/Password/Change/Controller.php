<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\User\Password\Change;

use Alaress\Password\Service;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class Controller extends Base
{

    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var Service
     */
    private $passwordService;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     * @param Service     $passwordService
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper, Service $passwordService)
    {
        $this->errorMapper = $errorMapper;
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
     * @throws MapperException
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
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
            $user->setPassword($this->passwordService->hash($this->getRequest()->post['new_password']));

            try {
                $this->userMapper->save($user);
                $this->getSessionSegment()->setFlash('isPasswordReset', true);
            } catch (\UnexpectedValueException $e) {
                // noop
            }
        }

        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('passwordChange.getChangePassword')
        );
    }
}
