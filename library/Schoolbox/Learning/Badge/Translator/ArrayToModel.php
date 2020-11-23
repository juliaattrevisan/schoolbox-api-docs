<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator;

use Schoolbox\Learning\Badge\Model;

/**
 * Translator for translating an array (generally from a request body) into a
 * model.
 */
class ArrayToModel
{
    /**
     * Convert an array to a model.
     *
     * @param array      $array
     * @param Model|null $model If provided, update fields in this model;
     *                          otherwise, create a new model
     * @return Model
     */
    public function __invoke(array $array, Model $model = null): Model
    {
        $model ??= new Model();

        // consider karriere/json-decoder for this?

        // $model = $this->setScalar($array, 'id', $model, 'setId');

        // $model = $this->setScalar($array, 'name', $model, 'setName');
        if (array_key_exists('name', $array)) {
            $model = $model->setName($array['name']);
        }

        // $model = $this->setScalar($array, 'body', $model, 'setBody');
        if (array_key_exists('body', $array)) {
            $model = $model->setBody($array['body']);
        }

        // $model = $this->setModel($array, 'category', 'id', new \Schoolbox\Learning\Badge\Category\Model()), $model, 'setCategory', )
        if (array_key_exists('category', $array)) {
            if (isset($array['category'])) {
                if (is_array($array['category']) && array_key_exists('id', $array['category'])) {
                    $model = $model->setCategory((new \Schoolbox\Learning\Badge\Category\Model())->setId($array['category']['id']));
                }
            } else {
                // raise a warning, but continue
                trigger_error(
                    'Attempting to set badge category to invalid null value: skipping',
                    E_USER_WARNING
                );
            }
        }

        // Set the image
        // $model = $this->setModel('image', 'hash', new Storage\Model(), [$model, 'setStorage']);

        // Or from a collector:
        // $


        // Set tags

        return $model;
    }


    /**
     * Helper method for setting a scalar value from an array into a model.
     *
     * If `$array[$arrayField]` exists, then `$model->$modelFieldSetter` will
     * be called with that value (even if it is null): otherwise, it will not
     * be called.
     *
     * @param array  $array            The array that is being set from
     * @param string $arrayField       The field in `$array` which contains the
     *                                 value
     * @param Model  $model            The model to update
     * @param string $modelFieldSetter The setter to call on `$model`
     * @return Model
     */
    private function setScalar(array $array, string $arrayField, Model $model, string $modelFieldSetter): Model
    {
        if (array_key_exists($arrayField, $array)) {
            $model = $model->$modelFieldSetter($array[$arrayField]);
        }
        return $model;
    }


    /**
     * Helper method for translating a scalar value from an array into a joined
     * model, and then setting that value into a model.
     *
     * If `$array[$arrayField][$arrayIdField]` exists, then:
     * * `$joinedModel->$joinedModelIdSetter` will be called with that value
     * * `$model->$modelFieldSetter` will be called with `$joinedModel`
     *
     * @param array  $array               The array that is being set from
     * @param string $arrayField          The field in `$array` which contains
     *                                    the array which contains the joined
     *                                    model's ID
     * @param string $arrayIdField        The field in `$array[$arrayField]`
     *                                    which contains the joined model's ID
     * @param Model  $model               The model to update
     * @param string $modelFieldSetter    The setter to call on `$model`
     * @param object $joinedModel         The joined model to set in `$model`
     * @param string $joinedModelIdSetter The setter to call on `$joinedModel`
     *                                    with the joined model's ID
     *
     * @return Model
     */
    private function setModel(
        array $array,
        string $arrayField,
        string $arrayIdField,
        Model $model,
        string $modelFieldSetter,
        object $joinedModel,
        string $joinedModelIdSetter
    ): Model {
        if (array_key_exists($arrayField, $array)) {
            if (isset($array[$arrayField])) {
                if (is_array($array[$arrayField]) && array_key_exists($arrayIdField, $array[$arrayField])) {
                    $model = $model->$modelFieldSetter(
                        $joinedModel->$joinedModelIdSetter($array[$arrayField][$arrayIdField])
                    );
                }
            } else {
                // Is this nullable?
                trigger_error(
                    'Attempting to set badge category to invalid null value: skipping',
                    E_USER_WARNING
                );
            }
        }
        return $model;
    }


    /**
     * Helper method for setting a date value from an array into a model.
     *
     * It's assumed that the date field will be in `DATE_ATOM` format.
     *
     * @param array  $array            The array that is being set from
     * @param string $arrayField       The field in `$array` which contains the
     *                                 value
     * @param Model  $model            The model to update
     * @param string $modelFieldSetter The setter to call on `$model`
     * @return Model
     */
    private function setDate(array $array, string $arrayField, Model $model, string $modelFieldSetter): Model
    {
        if (array_key_exists($arrayField, $array)) {
            $model = $model->$modelFieldSetter(
                isset($array[$arrayField])
                    ? new \Alaress_Date_Format($array[$arrayField], DATE_ATOM)
                    : null
            );
        }
        return $model;
    }
}
