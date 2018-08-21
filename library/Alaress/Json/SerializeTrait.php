<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json;
use Alaress\Json\Decorator\IDecorator;


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
     * @param IDecorator[] $decorators
     * @return array
     */
    public function jsonSerialize(IDecorator... $decorators)
    {
        $json = [];

        foreach ($decorators as $decorator) {
            $json = $decorator->decorate($json, $this);
        }

        return $json;
    }
}
