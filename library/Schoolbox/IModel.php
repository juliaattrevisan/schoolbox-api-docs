<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox;

/**
 * Defines the contract that ALL models must adhere to.
 *
 * Note that if there is a chance that a model may not respect this interface, then
 * don't add it here, create a new interface. eg it is preferable to have:
 * "class SomeModel implements IModel, ISoftDeletable, ISomething"
 * rather than have  "class SomeModel implements IModel" and not really implement the interface.
 */
interface IModel
{

    /**
     * Returns a collection that can only hold models of the type this function was called on.
     *
     * @return GenericCollection|IModel[]
     */
    public static function createCollection(): GenericCollection;
}
