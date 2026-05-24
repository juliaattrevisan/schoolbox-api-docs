<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json\Decorator;

use Alaress\Json\Serializable;
use ReflectionObject;

/**
 * The base decorator. According to the $map given at construction time, decorate the model given with properties
 * from the object.
 *
 * The map given will be a hydration map, which should be passed to any sub-models to instruct them as to
 * how they ought return their data for the context given
 *
 * Objects expecting to be serialized with this decoration SHOULD implement \Alaress\Json\Serializable.
 *
 * @package Alaress\Json\Decorator
 */
class PropertyDecorator implements IDecorator
{
    /** @var array */
    protected $map;

    /** @var IDecorator[] */
    private $decorators;


    /**
     * @param array|null $map
     * @param IDecorator ...$decorators
     */
    public function __construct(?array $map = null, IDecorator ...$decorators)
    {
        $this->map = $map;
        $this->decorators = $decorators;
    }


    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        $ro = new ReflectionObject($model);

        if ($this->map === null) {
            // obtain a default map if possible
            if ($model instanceof Serializable) {
                $map = $model->getDefaultJsonMap();
            } else {
                $map = [];
            }
        } else {
            // just use the map as we declared it
            $map = $this->map;
        }

        foreach ($map as $mapKey => $mapValue) {
            // integer mapKey means treat as scalar / default
            $field = \is_int($mapKey) ? $mapValue : $mapKey;

            $methodName = "get{$field}";

            if (!$ro->hasMethod($methodName)) {
                // be forgiving when it comes to nonexistent methods
                continue;
            }

            $method = $ro->getMethod($methodName);

            if (!$method->isPublic()) {
                // be forgiving when it comes to nonaccessible methods
                continue;
            }

            // obtain the value
            $result = $method->invoke($model);


            if ($result instanceof Serializable) {
                // recurse into the sub-object
                $subDecorator = \is_array($mapValue)
                    ? new static($mapValue)
                    : new static();

                $json[$field] = $result->jsonSerialize($subDecorator);

                // iterate any extra decorators and recursively decorate
                foreach ($this->decorators as $extraDecoration) {
                    $json[$field] = $extraDecoration->decorate($json[$field], $result);
                }
            } elseif ($result instanceof \JsonSerializable) {
                // do a vanilla json-serialize
                $json[$field] = $result->jsonSerialize();
            } else {
                // not something we know how to serialize? ok, just return as-is and let json_encode handle it
                $json[$field] = $result;
            }
        }

        // iterate any extra decorators and recursively decorate
        foreach ($this->decorators as $extraDecoration) {
            $json = $extraDecoration->decorate($json, $model);
        }

        return $json;
    }
}
