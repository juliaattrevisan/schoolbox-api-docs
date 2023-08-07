<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

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
    public function __construct(private ErrorMapper $errorMapper, private UserMapper $userMapper)
    {
    }


    /**
     * Provide form
     *
     * @param User $user
     */
    public function getCourse(User $user): void
    {
        $this->response = $this->responseAsJson([]);
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
