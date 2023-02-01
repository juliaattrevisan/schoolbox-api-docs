<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Router;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;
use Schoolbox\User;

/**
 * Unit spec model to array translator.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Router $router,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    /**
     * @param Model $unitSpec
     * @return array
     */
    public function __invoke(Model $unitSpec): array
    {
        return [
            'id'          => $unitSpec->getId(),
            'title'       => $unitSpec->getTitle(),
            '_links'      => [
                'folder' => '/homepage/' . $unitSpec->getFolder()->getId(),
                'modify' => $this->getRouteIfAuthorized(
                    'course.modifyCourse',
                    ['courseSpec' => $unitSpec, 'id' => $unitSpec->getId()],
                )
            ],
        ];
    }
}
