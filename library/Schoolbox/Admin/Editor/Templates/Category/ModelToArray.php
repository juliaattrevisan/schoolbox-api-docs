<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates\Category;


/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 *
 * This variant translates only the subset of fields that are relevant for
 * conveying that the user is the author of content.
 *
 * The information returned by this translator is guaranteed to be fit for all
 * audiences: it contains only publicly available information such as:
 * * the user's ID
 * * any names
 * * links to the profile page and profile image
 */
class ModelToArray
{
    /**
     * @param Model $model

     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id' => $model->getId(),
            'type' => $model->getType(),
        ];
    }
}
