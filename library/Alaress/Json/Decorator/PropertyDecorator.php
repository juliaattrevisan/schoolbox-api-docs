<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json\Decorator;

use Alaress\Json\Serializable;
use ReflectionObject;


class PropertyDecorator implements IDecorator
{
    /** @var array */
    protected $map;


    /**
     * @param array $map
     */
    public function __construct(array $map = null)
    {
        $this->map = $map;
    }


    /**
     * Decorate thew given input according
     *
     * @param mixed  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        $ro = new ReflectionObject($model);

        if ($this->map === null) {
            if ($model instanceof Serializable) {
                $map = $model->getDefaultJsonMap();
            } else {
                $map = [];
            }
        } else {
            $map = $this->map;
        }

        foreach ($map as $mapKey => $mapValue) {
            // integer mapKey means treat as scalar / default
            $field = \is_int($mapKey) ? $mapValue : $mapKey;

            $methodName = "get{$field}";

            if (!$ro->hasMethod($methodName)) {
                continue;
            }

            $method = $ro->getMethod($methodName);

            if (!$method->isPublic()) {
                continue;
            }

            // obtain the value
            $result = $method->invoke($model);


            if ($result instanceof Serializable) {

                $subDecorator = \is_array($mapValue)
                    ? new static($mapValue)
                    : new static();

                $json[$field] = $result->jsonSerialize($subDecorator);

            } elseif ($result instanceof \JsonSerializable) {
                $json[$field] = $result->jsonSerialize();

            } else {
                $json[$field] = $result;
            }
        }

        return $json;
    }

}