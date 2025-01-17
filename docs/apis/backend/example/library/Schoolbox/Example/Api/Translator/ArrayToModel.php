<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Example\Model;
use Schoolbox\User;
use TypeError;

/**
 * Translate array instances to a model, for input into APIs.
 */
class ArrayToModel extends Base
{
    /**
     * Translate the fields from `$array` into `$model`.
     *
     * Only fields which are actually intended to be modified by the API should
     * be set by this method.
     *
     * Note the type hints: the base `__invoke` accepts any object, but this
     * only works on `Model` instances. It isn't possible to make a parameter
     * type more restrictive in a subclass
     * (see https://en.wikipedia.org/wiki/Liskov_substitution_principle), hence
     * the type hinting instead of type definition.
     *
     * @param array               $array The array representation of the object,
     *                                   from the JSON request body.
     * @param (object&Model)|null $model The model (if any) which is being
     *                                   modified.
     * @return Model
     */
    public function __invoke(array $array, object $model = null): Model
    {
        // Fake strong type definitions here.
        if ($model && !$model instanceof Model) {
            throw new TypeError('$model must be an instance of "' . Model::class . '", got "' . gettype($model) . '"');
        }

        // Start assigning fields from the array to the model,
        // using the helpers in the superclass.
        $model ??= new Model();

        // This would set the model's ID, via `$model->setId($array['id'])`.
        // However, since the ID is not a mutable field of an object, it should
        // not be set in this method:
        // * if creating a new object, it won't yet have an ID
        // * if modifying an existing object, the ID will have already been
        //   loaded into `$model`
//        $model = $this->setScalar($array, 'id', $model, 'setId');

        // This sets the model's user, via:
        // `$model->setUser((new User())->setId($array['user']['id']))`.
        // Note that when setting a related model, no fields other than its ID
        // are relevant: the related model's ID is what is used in the foreign
        // key association.
        $model = $this->setModel($array, 'user', 'id', $model, 'setUser', new User(), 'setId');

        // These methods set the date of an object.
        // In practice, these fields won't be settable by an API, but they're
        // provided here as an example of how to set a date field.
        $model = $this->setDate($array, 'createdAt', $model, 'setCreatedAt');
        $model = $this->setDate($array, 'updatedAt', $model, 'setUpdatedAt');
        $model = $this->setDate($array, 'deletedAt', $model, 'setDeletedAt');

        // And return the object once done.
        return $model;
    }
}
