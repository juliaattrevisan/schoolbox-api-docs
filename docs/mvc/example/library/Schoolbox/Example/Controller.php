<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

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
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
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
            $user = $this->userMapper->findById($this->getRequest()->post['id']);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@example/submit.twig', [
            'user' => $user,
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Example Form Output'));
    }
}
