<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel;

use Schoolbox\Learning\Moment\Evidence;

/**
 * Array-to-model translator which filters out fields which may not be mutated
 * by the create or update evidence API calls.
 *
 * The only fields which may be mutated are:
 * * body
 * * file
 * * storage
 * * filename
 */
class MutableFieldsOnly extends AllFields
{
    private const MUTABLE_FIELDS = [
        'body'     => true,
        'file'     => true,
        'storage'  => true,
        'filename' => true,
    ];

    /**
     * @inheritdoc
     */
    public function __invoke(array $json, ?Evidence\Model $model = null): Evidence\Model
    {
        return parent::__invoke(
            array_intersect_key(
                $json,
                self::MUTABLE_FIELDS,
            ),
            $model
        );
    }
}
