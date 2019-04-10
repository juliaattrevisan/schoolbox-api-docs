<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment\ModelDecorator;

use Schoolbox\Discussion\Comment\Model;
use Schoolbox\Discussion\Comment\ModelDecorator\BaseDecorator;
use Schoolbox\Discussion\Comment\ModelDecorator\IFactory;
use Schoolbox\Discussion\Thread\Context\IContext;

/**
 * Factory for model decorators for the comms variant of the discussion thread
 * comments module.
 */
class Factory implements IFactory
{
    /**
     * @param Model    $model
     * @param IContext $context
     * @return BaseDecorator
     */
    public function build(Model $model, IContext $context): BaseDecorator
    {
        return new Decorator($model, $context);
    }
}
