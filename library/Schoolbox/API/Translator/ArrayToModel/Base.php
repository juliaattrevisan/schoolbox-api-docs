<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Translator\ArrayToModel;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage\Model as StorageModel;
use TypeError;

/**
 * A base class for array-to-model translators.
 *
 * Provides helper methods for setting values from an array into a model.
 */
abstract class Base implements ITranslator
{
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
     * @param bool   $trimStringValues If true, and the value is a string:
     *                                 trim the value before setting the field
     * @return object
     */
    protected function setScalar(
        array $array,
        string $arrayField,
        object $model,
        string $modelFieldSetter,
        bool $trimStringValues = true
    ): object {
        if (array_key_exists($arrayField, $array)) {
            $value = $array[$arrayField];
            if ($trimStringValues && is_string($value)) {
                $value = trim($value);
            }
            $model = $this->setFieldOnModel($model, $modelFieldSetter, $value);
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
                    $model = $this->setFieldOnModel(
                        $model,
                        $modelFieldSetter,
                        $joinedModel->$joinedModelIdSetter($array[$arrayField][$arrayIdField])
                    );
                }
            } else {
                $model = $this->setFieldOnModel($model, $modelFieldSetter, null);
            }
        }
        return $model;
    }


    /**
     * Helper method for translating a collection of values from an array into a
     * collection of joined models, and then setting that collection into a
     * model.
     *
     * @example [['id' => 1], ['id' => 2], ['id' => 3]] will be translated into
     *          a collection of objects:
     *          the first with ID=1, then ID=2, then ID=3
     *
     * @param array      $array                 The array that is being set from
     * @param string     $arrayField            The field in `$array` which has
     *                                          the list of joined model IDs
     * @param string     $arrayIdField          The field in each item in
     *                                          `$array[$arrayField]` which has
     *                                          the joined model's ID
     * @param object     $model                 The model to update
     * @param string     $modelFieldSetter      The setter to call on `$model`
     * @param Collection $joinedModelCollection The value to set in `$model`
     * @param object     $joinedModelTemplate   A template object: clones of
     *                                          this object are used to populate
     *                                          `$joinedModelCollection`
     * @param string     $joinedModelIdSetter   The setter to call on the clone
     *                                          of `$joinedModelTemplate`
     *                                          with the joined model's ID
     * @return object
     */
    protected function setCollection(
        array $array,
        string $arrayField,
        string $arrayIdField,
        object $model,
        string $modelFieldSetter,
        Collection $joinedModelCollection,
        object $joinedModelTemplate,
        string $joinedModelIdSetter
    ): object {
        if (array_key_exists($arrayField, $array)) {
            if (isset($array[$arrayField])) {
                foreach ($array[$arrayField] as $curriculumCode) {
                    //if $arrayIdField is blank, then this is an array, with no extra data
                    if ('' === $arrayIdField) {
                        $joinedModelCollection->add(
                            (clone $joinedModelTemplate)->$joinedModelIdSetter($curriculumCode)
                        );
                    } else {
                        //there are 'id' here, so lets use them...
                        $joinedModelCollection->add(
                            (clone $joinedModelTemplate)->$joinedModelIdSetter($curriculumCode[$arrayIdField])
                        );
                    }
                }
                $model = $model->$modelFieldSetter($joinedModelCollection);
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
            $model = $this->setFieldOnModel(
                $model,
                $modelFieldSetter,
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
     * * if it's an array with the key "hash": it will create a new storage file
     *   with that hash, and call `$model->$modelFieldSetter` with that file
     *   (just as `setModel` would work)
     * * if it's a storage triplet (i.e. "storage:hash:filename"):
     *  `$collectorFactory` will build a collector which can locate that file,
     *   then `$model->$modelFieldSetter` will be called with the storage object
     *   it creates
     *
     * If `$array[$arrayField]` is null, then:
     * * `$model->$modelFieldSetter` will be called with `null`
     *
     * @param array            $array            The array being set from
     * @param string           $arrayField       The field in `$array` which
     *                                           contains either:
     *                                           * a storage triplet
     *                                           * an array with the key "hash"
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
                $model = $this->setFieldOnModel($model, $modelFieldSetter, null);
            } elseif (is_array($array[$arrayField])) {
                $model = $this->setModel(
                    $array,
                    $arrayField,
                    'hash',
                    $model,
                    $modelFieldSetter,
                    new StorageModel(),
                    'setHash'
                );
            } else {
                $file = $collectorFactory->build([$array[$arrayField]])->getFirst();
                if ($file) {
                    $model = $this->setFieldOnModel($model, $modelFieldSetter, $file);
                }
            }
        }
        return $model;
    }


    /**
     * Helper for ensuring that the given model is an instance of the given class.
     *
     * In the absence of generics: use this to verify that the object passed
     * into the translator is recognised by this translator.
     *
     * @param object $model
     * @param string $className
     * @throws TypeError if `$model` isn't an instance of `$classname`
     */
    protected function assertModelIsInstanceOf(object $model, string $className): void
    {
        if (!is_a($model, $className, true)) {
            throw new TypeError(
                sprintf(
                    'Model must be an instance of %s: got %s instead',
                    $className,
                    get_class($model)
                )
            );
        }
    }


    /**
     * Helper for setting a field on the model.
     *
     * This helper is tolerant of `TypeError` errors when setting the model's
     * field value: it will instead raise a `E_USER_WARNING` and leave the field
     * value as it was.
     *
     * TODO consider whether to actually be lenient of type errors here.
     *      I can see a case for not silently ignoring values that are of an
     *      invalid type.
     *
     * @param object $model
     * @param string $modelFieldSetter
     * @param mixed  $value
     * @return object
     */
    private function setFieldOnModel(object $model, string $modelFieldSetter, $value): object
    {
        try {
            return $model->$modelFieldSetter($value);
        } catch (TypeError $e) {
            trigger_error(
                sprintf(
                    'Attempting to call "%s::%s" with invalid parameter of type "%s": skipping',
                    get_class($model),
                    $modelFieldSetter,
                    (is_object($value) ? get_class($value) : gettype($value))
                ),
                E_USER_WARNING
            );
            return $model;
        }
    }
}
