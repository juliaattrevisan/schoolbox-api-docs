<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private UserMapper $userMapper
    ) {
    }


    /**
     * Provide form
     *
     * @param User $user
     */
    public function getExample(User $user): void
    {
        $this->response = $this->responseFromTemplate('@example/index.twig', ['user' => $user], 'Example');
    }


    /**
     * Process the form vars
     */
    public function postExample(): void
    {
        try {
            $user = $this->userMapper->findById($this->requestPost['id']);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->response = $this->responseFromTemplate(
            '@example/submit.twig',
            [
                'user' => $user,
                'var1' => $this->requestPost['var1'],
            ],
            'Example Form Output',
        );
    }
}
