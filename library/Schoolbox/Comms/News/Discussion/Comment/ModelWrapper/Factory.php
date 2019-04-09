<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment\ModelWrapper;

use Schoolbox\Discussion\Comment\Model;
use Schoolbox\Discussion\Comment\ModelWrapper\BaseWrapper;
use Schoolbox\Discussion\Comment\ModelWrapper\IFactory;
use Schoolbox\Discussion\Thread\Context\IContext;

/**
 * Factory for model wrappers for the comms variant of the discussion thread
 * comments module.
 */
class Factory implements IFactory
{
    /**
     * @param Model $model
     * @return BaseWrapper
     */
    public function build(Model $model, IContext $context): BaseWrapper
    {
        return new Wrapper($model, $context);
    }
}
