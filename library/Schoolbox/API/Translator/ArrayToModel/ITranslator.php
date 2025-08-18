<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Translator\ArrayToModel;

/**
 * An interface for translators from an array (likely from an API request body)
 * to a model class (for persistence by a mapper instance).
 */
interface ITranslator
{
    /**
     * Searches `$array` for fields which correspond to a model property, and
     * sets them into the model.
     *
     * @param array       $array Fields as received in an API request body
     * @param object|null $model If provided, update fields in this model;
     *                           otherwise, the implementation must create a new
     *                           model
     * @return object `$model` with the fields from `$array` applied to it
     */
    public function __invoke(array $array, ?object $model = null): object;
}
