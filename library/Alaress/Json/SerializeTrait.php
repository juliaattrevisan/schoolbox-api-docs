<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json;
use ReflectionObject;


/**
 * Trait SerializeTrait. Should be used to realize \JsonSerializable on models.
 *
 * @package Alaress\Json
 */
trait SerializeTrait
{

    /**
     * @return array
     */
    abstract protected function getDefaultJsonMap(): array;


    /**
     * The map given will be a hydration map, which should be passed to any sub-models to instruct them as to
     * how they ought return their data for the context given
     *
     * @param array $map
     * @return array
     */
    public function jsonSerialize(array $map = null)
    {
        if ($map === null) {
            $map = $this->getDefaultJsonMap();
        }

        $json = [];

        $ro = new ReflectionObject($this);

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
            $result = $method->invoke($this);

            if ($result instanceof \JsonSerializable) {
                if (\is_array($mapValue)) {
                    $json[$field] = $result->jsonSerialize($mapValue);
                } else {
                    $json[$field] = $result->jsonSerialize();
                }
            } else {
                $json[$field] = $result;
            }

        }

        return $json;
    }
}