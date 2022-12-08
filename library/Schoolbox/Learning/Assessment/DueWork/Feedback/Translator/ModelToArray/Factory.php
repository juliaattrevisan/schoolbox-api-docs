<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\Translator\ModelToArray;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Translator\ModelToArray;
use Schoolbox\User;

/**
 * Factory for building a standard translator
 */
class Factory
{
    public function __construct(
        private Router $router,
        private Authorizer $authorizer,
        private User $user
    ) {
    }

    public function build(): ModelToArray
    {
        return new ModelToArray(
            new \Schoolbox\Learning\Assessment\ModelToArray(),
            new \Schoolbox\User\Translator\Author\ModelToArray(),
            new \Schoolbox\Learning\Assessment\Translator\Links(
                $this->router,
                $this->authorizer,
                $this->user
            )
        );
    }
}
