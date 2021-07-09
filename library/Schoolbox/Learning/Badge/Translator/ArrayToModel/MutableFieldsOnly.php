<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ArrayToModel;

/**
 * Array-to-model translator which filters out fields which may not be mutated
 * by the create or update API calls.
 *
 * The only fields which may be mutated are:
 * * name
 * * body
 * * icon
 * * image
 * * category
 * * tags
 * * curriculumNodes
 */
class MutableFieldsOnly extends AllFields
{
    private const MUTABLE_FIELDS = [
        'name'              => true,
        'body'              => true,
        'icon'              => true,
        'image'             => true,
        'allow-as-goal'     => true,
        'category'          => true,
        'tags'              => true,
        'yearLevelIds'      => true,
        'curriculumNodes'   => true,
        'achievementExpiry' => true,
        'goalExpiry'        => true,
    ];


    /**
     * @inheritdoc
     */
    public function __invoke(array $array, object $model = null): object
    {
        return parent::__invoke(
            array_intersect_key(
                $array,
                self::MUTABLE_FIELDS,
            ),
            $model
        );
    }
}
