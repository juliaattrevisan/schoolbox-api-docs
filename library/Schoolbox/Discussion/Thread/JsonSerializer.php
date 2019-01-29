<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

/**
 * JSON Serializer for `Model` objects.
 */
class JsonSerializer
{
    /**
     * Serializes this object as an array for JSON output.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id'      => $model->getId(),
            'open'    => $model->getOpen(),
            'created' => $model->getCreatedAt()->toRfc3339String(),
            'updated' => $model->getUpdatedAt()->toRfc3339String(),
        ];
    }
}
