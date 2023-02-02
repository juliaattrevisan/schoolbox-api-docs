<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Translator;

use Schoolbox\Resource\Folder\Model;

/**
 * Interface for model-to-array translators for folders.
 */
interface IModelToArray
{
    /**
     * Converts this folder to a model representation.
     *
     * @param Model $folder
     * @return array
     */
    public function __invoke(Model $folder): array;
}
