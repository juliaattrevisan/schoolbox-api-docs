<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ArrayToModel;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Curriculum\Node\Model as CurriculumNode;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Badge\Category\Model as BadgeCategory;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\User;
use Schoolbox\YearLevel\Model as YearLevel;

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
        $model ??= new Model();
        $model = $this->setDate($array, 'createdAt', $model, 'setCreatedAt');
        $model = $this->setDate($array, 'updatedAt', $model, 'setUpdatedAt');
        $model = $this->setDate($array, 'deletedAt', $model, 'setDeletedAt');
        $model = $this->setScalar($array, 'id', $model, 'setId');
        $model = $this->setScalar($array, 'name', $model, 'setName');
        $model = $this->setScalar($array, 'body', $model, 'setBody');
        $model = $this->setScalar($array, 'icon', $model, 'setIcon');
        $model = $this->setScalar($array, 'achievementExpiry', $model, 'setAchievementExpiry');
        $model = $this->setScalar($array, 'goalExpiry', $model, 'setGoalExpiry');
        $model = $this->setFile($array, 'image', $model, 'setImage', $this->collectorFactory);
        $model = $this->setScalar($array, 'allow-as-goal', $model, 'setAllowAsGoal');
        $model = $this->setScalar($array, 'animate', $model, 'setAnimate');
        $model = $this->setModel(
            $array,
            'category',
            'id',
            $model,
            'setCategory',
            new BadgeCategory(),
            'setId'
        );

        // TODO set tags

        // Set year levels
        $this->setCollection(
            $array,
            'yearLevelIds',
            'id',
            $model,
            'setYearLevels',
            new GenericCollection(YearLevel::class),
            new YearLevel(),
            'setId'
        );

        // Set curriculum nodes
        $this->setCollection(
            $array,
            'curriculumNodes',
            '',
            $model,
            'setCurriculumNodes',
            new GenericCollection(CurriculumNode::class),
            new CurriculumNode(),
            'setId'
        );

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
