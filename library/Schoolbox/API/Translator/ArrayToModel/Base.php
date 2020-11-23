<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Translator\ArrayToModel;

use Alaress_Date_Format;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception as ResourceException;
use TypeError;

/**
 * A base class for translators from an array (likely from an API request body)
 * to a model class (for persistence by a mapper instance).
 */
abstract class Base
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
    abstract public function __invoke(array $array, object $model = null): object;


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
     * @param object $model            The model to update
     * @param string $modelFieldSetter The setter to call on `$model`
     * @return object
     */
    protected function setScalar(array $array, string $arrayField, object $model, string $modelFieldSetter): object
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
     * If `$array[$arrayField]` is null, then:
     * * `$model->$modelFieldSetter` will be called with `null`
     *
     * @param array  $array               The array that is being set from
     * @param string $arrayField          The field in `$array` which contains
     *                                    the array which contains the joined
     *                                    model's ID
     * @param string $arrayIdField        The field in `$array[$arrayField]`
     *                                    which contains the joined model's ID
     * @param object $model               The model to update
     * @param string $modelFieldSetter    The setter to call on `$model`
     * @param object $joinedModel         The joined model to set in `$model`
     * @param string $joinedModelIdSetter The setter to call on `$joinedModel`
     *                                    with the joined model's ID
     *
     * @return object
     * @throws TypeError if attempting to set `$model->$modelFieldSetter` to
     *                   null, when it is not nullable
     */
    protected function setModel(
        array $array,
        string $arrayField,
        string $arrayIdField,
        object $model,
        string $modelFieldSetter,
        object $joinedModel,
        string $joinedModelIdSetter
    ): object {
        if (array_key_exists($arrayField, $array)) {
            if (isset($array[$arrayField])) {
                if (is_array($array[$arrayField]) && array_key_exists($arrayIdField, $array[$arrayField])) {
                    $model = $model->$modelFieldSetter(
                        $joinedModel->$joinedModelIdSetter($array[$arrayField][$arrayIdField])
                    );
                }
            } else {
                $model = $model->$modelFieldSetter(null);
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
     * @param object $model            The model to update
     * @param string $modelFieldSetter The setter to call on `$model`
     * @return object
     */
    protected function setDate(array $array, string $arrayField, object $model, string $modelFieldSetter): object
    {
        if (array_key_exists($arrayField, $array)) {
            $model = $model->$modelFieldSetter(
                isset($array[$arrayField])
                    ? new Alaress_Date_Format($array[$arrayField], DATE_ATOM)
                    : null
            );
        }
        return $model;
    }


    /**
     * Helper method for setting a storage file into a model.
     *
     * If `$array[$arrayField]` exists and is set, then:
     * * `$collectorFactory` will build a collector which can locate that file
     * * `$model->$modelFieldSetter` will be called with the storage object it
     *   creates
     *
     * If `$array[$arrayField]` is null, then:
     * * `$model->$modelFieldSetter` will be called with `null`
     *
     * @param array            $array            The array being set from
     * @param string           $arrayField       The field in `$array` which
     *                                           contains the storage triplet
     * @param object           $model            The model to update
     * @param string           $modelFieldSetter The setter to call on `$model`
     * @param CollectorFactory $collectorFactory The object which locates a
     *                                           previously uploaded file in
     *                                           the storage system.
     * @return object
     * @throws ResourceException if `$collectorFactory` can't locate the file
     */
    protected function setFile(
        array $array,
        string $arrayField,
        object $model,
        string $modelFieldSetter,
        CollectorFactory $collectorFactory
    ): object {
        if (array_key_exists($arrayField, $array)) {
            if (!isset($array[$arrayField])) {
                $model = $model->$modelFieldSetter(null);
            } else {
                $file = $collectorFactory->build([$array[$arrayField]])->getFirst();
                if ($file) {
                    $model = $model->$modelFieldSetter($file);
                }
            }
        }
        return $model;
    }
}
