<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Moderation;

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
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'user' => $user
        ])->setTitle('Example'));
    }


    /**
     * Process the form vars
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postExample()
    {
        try {
            $user = $this->userMapper->findById($this->getRequest()->post['id']);
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/submit.twig', [
            'user' => $user,
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Example Form Output'));
    }
}