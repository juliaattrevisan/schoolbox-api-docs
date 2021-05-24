<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\ModelDecorator;

use Schoolbox\Discussion\Thread\Context\IThreadLocation;
use Schoolbox\Discussion\Thread\Model;

/**
 * Interface for the factory for building a wrapped model out of a model.
 */
interface IFactory
{
    /**
     * Build a wrapped model out of a model.
     *
     * @param Model    $model
     * @param IThreadLocation $context
     * @return BaseDecorator
     */
    public function build(Model $model, IThreadLocation $context): BaseDecorator;
}
