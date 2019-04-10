<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment\ModelDecorator;

use Schoolbox\Discussion\Comment\Model;
use Schoolbox\Discussion\Thread\Context\IContext;

/**
 * Interface for the factory for building a decorated model out of a model.
 */
interface IFactory
{
    /**
     * Build a wrapped model out of a model.
     *
     * @param Model    $model
     * @param IContext $context
     * @return BaseDecorator
     */
    public function build(Model $model, IContext $context): BaseDecorator;
}
