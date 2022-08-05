<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Curriculum\Node\Model;

/**
 * Translator for translating a curriculum node array JSON into a model.
 */
class ArrayToModel extends Base
{
    /**
     * @param array       $array
     * @param object|null $model
     * @return Model
     */
    public function __invoke(array $array, object $model = null): object
    {
        $model ??= new Model();
        $model = $this->setScalar($array, 'id', $model, 'setId');
        $model = $this->setScalar($array, 'name', $model, 'setName');
        $model = $this->setScalar($array, 'code', $model, 'setCode');
        $model = $this->setScalar($array, 'reference', $model, 'setReference');
        $model = $this->setScalar($array, 'description', $model, 'setDescription');
        $model = $this->setScalar($array, 'enabled', $model, 'setEnabled');
        $model = $this->setModel(
            $array,
            'parent',
            'id',
            $model,
            'setParent',
            new Model(),
            'setId'
        );
        $model = $this->setModel(
            $array,
            'learningArea',
            'id',
            $model,
            'setLearningArea',
            new Model(),
            'setId'
        );
        $model = $this->setModel(
            $array,
            'learningLevel',
            'id',
            $model,
            'setLearningLevel',
            new Model(),
            'setId'
        );
        $model = $this->setScalar($array, 'order', $model, 'setOrder');
        $this->assertModelIsInstanceOf($model, Model::class);
        return $model;
    }
}
