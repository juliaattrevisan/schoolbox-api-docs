<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates\Category;

/**
 * Translator for translating a category model into an array, ready for JSON
 * encoding.
 */
class ModelToArray
{
    /**
     * @param Model $model
     *
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id' => $model->getId(),
            'name' => $model->getName(),
        ];
    }
}
