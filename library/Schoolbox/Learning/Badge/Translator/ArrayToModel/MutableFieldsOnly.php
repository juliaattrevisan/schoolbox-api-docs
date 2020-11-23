<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ArrayToModel;

use Schoolbox\Learning\Badge\Model;

/**
 * Array-to-model translator which filters out fields which may not be mutated
 * by the create or update API calls.
 *
 * The only fields which may be mutated are:
 * * name
 * * body
 * * image
 * * category
 * * tags
 * * curriculumCodes
 */
class MutableFieldsOnly extends AllFields
{
    private const MUTABLE_FIELDS = [
        'name'            => true,
        'body'            => true,
        'image'           => true,
        'category'        => true,
        'tags'            => true,
        'curriculumCodes' => true,
    ];


    /**
     * @inheritdoc
     */
    public function __invoke(array $array, Model $model = null): Model
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
