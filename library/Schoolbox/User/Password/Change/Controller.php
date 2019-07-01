<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\User\Password\Change;

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
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getChangePassword()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@passwordChange/index.twig', [])->setTitle('Change Password'));
    }


    /**
     * Process the form vars
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postChangePassword()
    {
        /* Check that this user still exists at the time of post
         */
        try {
            $user = $this->userMapper->findById($this->getUser()->getId());
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        new \Alaress_Probe(
            $this->getRequest()->post['current_password'],
            $this->getRequest()->post['new_password'],
            $this->getRequest()->post['confirm_password']
        );
    }
}
