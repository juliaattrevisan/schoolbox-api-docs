<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Discussion\Thread\ModelDecorator;

use Schoolbox\Discussion\Thread\Context\IThreadLocation;
use Schoolbox\Discussion\Thread\Model;
use Schoolbox\Discussion\Thread\ModelDecorator\BaseDecorator;
use Schoolbox\Discussion\Thread\ModelDecorator\IFactory;

/**
 * Factory for model decorators for the evidence variant of the discussion
 * thread module.
 */
class Factory implements IFactory
{
    /**
     * @param Model    $model
     * @param IThreadLocation $context
     * @return BaseDecorator
     */
    public function build(Model $model, IThreadLocation $context): BaseDecorator
    {
        return new Decorator($model, $context);
    }
}
