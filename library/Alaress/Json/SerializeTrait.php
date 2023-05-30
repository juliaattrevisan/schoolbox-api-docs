<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json;

use Alaress\Json\Decorator\IDecorator;
use Alaress\Json\Decorator\PropertyDecorator;
use ReturnTypeWillChange;

/**
 * Trait SerializeTrait. Should be used to realize \JsonSerializable on models.
 *
 * @package Alaress\Json
 */
trait SerializeTrait
{

    /**
     * @param IDecorator ...$decorators
     * @return array
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(IDecorator ...$decorators): array
    {
        if (!\count($decorators)) {
            // to preserve default json_encode behaviour, enforce the property decorator
            $decorators = [ new PropertyDecorator() ];
        }

        $json = [];

        foreach ($decorators as $decorator) {
            $json = $decorator->decorate($json, $this);
        }

        return $json;
    }
}
