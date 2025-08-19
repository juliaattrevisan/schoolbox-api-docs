<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Translator\ArrayToModel;

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
        'tags'     => true,
        'type'     => true,
    ];

    private bool $curriculumTaggingEnabled = true;

    /**
     * @inheritdoc
     */
    public function __invoke(array $json, ?object $model = null): object
    {
        $fields = self::MUTABLE_FIELDS;
        if ($this->curriculumTaggingEnabled) {
            $fields = array_merge(
                $fields,
                ['curriculumNodes' => $this->curriculumTaggingEnabled,],
            );
        }
        return parent::__invoke(
            array_intersect_key(
                $json,
                $fields,
            ),
            $model
        );
    }


    /**
     *
     * @param bool $curriculumTaggingEnabled
     */
    public function setCurriculumTaggingEnabled(bool $curriculumTaggingEnabled): void
    {
        $this->curriculumTaggingEnabled = $curriculumTaggingEnabled;
    }
}
