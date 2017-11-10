<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Example;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
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
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper)
    {
        $this->setErrorMapper($errorMapper);
        $this->setUserMapper($userMapper);
    }

    /**
     * Provide form
     *
     * @param User $user
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getExample(User $user)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@example/index.twig', [
            'user' => $user
        ])->setTitle('Example'));
    }

    /**
     * Process the form vars
     */
    public function postExample()
    {
        try {
            $user = $this->getUserMapper()->findById($this->getRequest()->post['id']);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@example/submit.twig', [
            'user' => $user,
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Example Form Output'));
    }

    /**
     * @return UserMapper
     */
    private function getUserMapper(): UserMapper
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    private function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    private function getErrorMapper(): ErrorMapper
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    private function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}