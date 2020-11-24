<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ArrayToModel;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Badge\Category\Model as BadgeCategory;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\User;

/**
 * Translator for translating an array (generally from a request body) into a
 * model.
 *
 * Used when translating JSON passed in a request body into a model, so that it
 * may be persisted.
 */
class AllFields extends Base
{
    /**
     * @var CollectorFactory
     */
    private CollectorFactory $collectorFactory;


    /**
     * Constructor
     *
     * @param CollectorFactory $collectorFactory
     */
    public function __construct(CollectorFactory $collectorFactory)
    {
        $this->collectorFactory = $collectorFactory;
    }


    /**
     * Convert an array to a model.
     *
     * @param array             $array
     * @param object|Model|null $model
     * @return object|Model
     * @throws ResourceException
     */
    public function __invoke(array $array, object $model = null): object
    {
        /** @var Model $model */
        $model ??= new Model();
        $model = $this->setDate($array, 'createdAt', $model, 'setCreatedAt');
        $model = $this->setDate($array, 'updatedAt', $model, 'setUpdatedAt');
        $model = $this->setDate($array, 'deletedAt', $model, 'setDeletedAt');
        $model = $this->setScalar($array, 'id', $model, 'setId');
        $model = $this->setScalar($array, 'name', $model, 'setName');
        $model = $this->setScalar($array, 'body', $model, 'setBody');
        $model = $this->setFile($array, 'image', $model, 'setImage', $this->collectorFactory);
        $model = $this->setModel(
            $array,
            'category',
            'id',
            $model,
            'setCategory',
            new BadgeCategory(),
            'setId'
        );

        // Set tags

        // Set curriculum nodes
        if (array_key_exists('curriculumNodes', $array)) {
            if (isset($array['curriculumNodes'])) {
                $collection = new GenericCollection(\Schoolbox\Curriculum\Node\Model::class);
                foreach ($array['curriculumNodes'] as $curriculumCode) {
                    $collection->add((new \Schoolbox\Curriculum\Node\Model())->setId($curriculumCode['id']));
                }
                $model = $model->setCurriculumNodes($collection);
            } else {
                $model = $model->setCurriculumNodes(null);
            }
        }

        // Set created/updated by
        $model = $this->setModel(
            $array,
            'createdBy',
            'id',
            $model,
            'setCreatedBy',
            new User(),
            'setId'
        );
        $model = $this->setModel(
            $array,
            'updatedBy',
            'id',
            $model,
            'setUpdatedBy',
            new User(),
            'setId'
        );

        // If at the end of it all it isn't a model: raise an error
        $this->assertModelIsInstanceOf($model, Model::class);
        return $model;
    }
}
