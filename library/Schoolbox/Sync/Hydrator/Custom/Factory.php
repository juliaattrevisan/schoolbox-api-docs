<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use Schoolbox\Collection;
use Schoolbox\Sync;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use UnexpectedValueException;

/**
 * Factory for building a Hydrator instance from a Model object.
 */
class Factory
{
    /**
     * Builds a hydrator from its model.
     *
     * @param Model $model
     *
     * @return HydratorInterface
     * @throws UnexpectedValueException if class doesn't exist, or it's not instanceof HydratorInterface
     */
    public function produceFromModel(Model $model)
    {
        $class = $model->getClass();
        if (class_exists($class)) {
            // TODO inject whatever creation parameters may be required
            $hydrator = new $class();
            if ($hydrator instanceof HydratorInterface) {
                return $hydrator;
            }
            throw new UnexpectedValueException(
                sprintf('"%s" does not implement "%s"', $class, HydratorInterface::class)
            );
        }
        throw new UnexpectedValueException(sprintf('"%s" does not exist', $class));
    }

    /**
     * Builds a hydrator from a collection of models
     *
     * @param Model[] $collection
     *
     * @return HydratorInterface[]
     */
    public function produceFromCollection($collection)
    {
        $hydrators = new Collection();
        foreach ($collection as $model) {
            try {
                if ($model instanceof Model) {
                    $hydrators->add($this->produceFromModel($model));
                }
            } catch (UnexpectedValueException $e) {
                // noop
            }
        }
        return $hydrators;
    }
}
