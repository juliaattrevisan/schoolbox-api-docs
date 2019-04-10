<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread\ModelDecorator;

use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread\Model;
use Schoolbox\Discussion\Thread\ModelDecorator\BaseDecorator;
use Schoolbox\Discussion\Thread\ModelDecorator\IFactory;

/**
 * Factory for model decorators for the comms variant of the discussion thread
 * module.
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
