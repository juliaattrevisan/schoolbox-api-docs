<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Example\Model;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;

/**
 * Translate model instances to an array, for output by APIs.
 */
class ModelToArray
{
    /**
     * This trait is commonly used in model-to-array translators:
     * it exposes `getRouteIfAuthorized` so that the translator may generate
     * Aura routes in a consistent manner.
     *
     * Note that it requires that three properties be assigned:
     * * `$routeGenerator`
     * * `$authorizer`
     * * `$currentUser`
     */
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator      $routeGenerator
     * @param Authorizer     $authorizer
     * @param User           $currentUser
     * @param UserTranslator $userTranslator Translators may use other translators to translate sub-objects: for
     *                                       example, use an instance of this translator to translate the user within
     *                                       an example model.
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private UserTranslator $userTranslator,
    ) {
    }

    /**
     * Translates a model to an array.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        // Parameters for the routes relevant to the model.
        $routeParams = [
            'id'   => $model->getId(),
            'item' => $model,
        ];

        return [
            'id' => $model->getId(),
            // Sub-models within a model should be translated using a translator
            // specific to the sub-model.
            // In this case, one already exists.
            'user' => $model->getUser()
                ? $this->userTranslator->__invoke($model->getUser())
                : null,

            // Dates should be output in the standard JSON schema date format:
            // https://json-schema.org/understanding-json-schema/reference/string#dates-and-times
            'updatedAt' => $model->getUpdatedAt()?->toRfc3339String(),
            'deletedAt' => $model->getDeletedAt()?->toRfc3339String(),
            'createdAt' => $model->getCreatedAt()?->toRfc3339String(),

            // Links: various links relevant to the model.
            // These are the reason the `GetRouteIfAuthorized` trait is
            // included.
            '_links' => [
                'read'   => $this->getRouteIfAuthorized('exampleApi.getItem', $routeParams),
                'update' => $this->getRouteIfAuthorized('exampleApi.putItem', $routeParams),
                'delete' => $this->getRouteIfAuthorized('exampleApi.deleteItem', $routeParams),
            ],
        ];
    }
}
