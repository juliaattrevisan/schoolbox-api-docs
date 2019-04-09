<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread\ModelWrapper;

use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread\Model;
use Schoolbox\Discussion\Thread\ModelWrapper\BaseWrapper;
use Schoolbox\Discussion\Thread\ModelWrapper\IFactory;

/**
 * Factory for model wrappers for the comms variant of the discussion thread
 * module.
 */
class Factory implements IFactory
{
    /**
     * @param Model    $model
     * @param IContext $context
     * @return BaseWrapper
     */
    public function build(Model $model, IContext $context): BaseWrapper
    {
        return new Wrapper($model, $context);
    }
}
