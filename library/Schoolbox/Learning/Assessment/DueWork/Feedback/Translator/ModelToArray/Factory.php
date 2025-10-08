<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\Translator\ModelToArray;

use Alaress\Authorizer\Authorizer;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Aura\Router\Generator;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Translator\ModelToArray;
use Schoolbox\Learning\Assessment\Translator\AssessmentForFeed;
use Schoolbox\Learning\Assessment\Translator\Links;
use Schoolbox\User;
use Schoolbox\Config\Loader as ConfigLoader;

/**
 * Factory for building a standard translator
 */
class Factory
{
    public function __construct(
        private ConfigLoader $configLoader,
        private Generator $routeGenerator,
        private Authorizer $authorizer,
        private User $user
    ) {
    }

    public function build(): ModelToArray
    {
        return new ModelToArray(
            $this->configLoader,
            new AssessmentForFeed(),
            new User\Translator\Author\ModelToArray(),
            new Links(
                $this->routeGenerator,
                $this->authorizer,
                $this->user
            ),
            new LinkDecorator($this->routeGenerator, $this->authorizer, $this->user),
            new ScrubAdapter()
        );
    }
}
