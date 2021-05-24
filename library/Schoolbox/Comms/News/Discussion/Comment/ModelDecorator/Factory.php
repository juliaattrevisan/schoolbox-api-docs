<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment\ModelDecorator;

use Schoolbox\Discussion\Comment\Model;
use Schoolbox\Discussion\Comment\ModelDecorator\BaseDecorator;
use Schoolbox\Discussion\Comment\ModelDecorator\IFactory;
use Schoolbox\Discussion\Thread\Context\IThreadLocation;

/**
 * Factory for model decorators for the comms variant of the discussion thread
 * comments module.
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
