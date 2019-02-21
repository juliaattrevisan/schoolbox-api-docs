<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Mapper\Traits\Model;

use Schoolbox\GenericCollection;

/**
 * While it's expected that all models will use this trait, it's implemented separately
 * to support re-use by other non-model classes
 */
trait CreateCollection
{

    /**
     * Creates a collection that may only contain items of the class it is used in
     */
    public static function createCollection(): GenericCollection
    {
        try {
            $collection = new GenericCollection(static::class);
        } catch (\UnexpectedValueException $e) {
            // This logically cannot happen, but just in case something changes elsewhere.
            \trigger_error($e->getMessage());
            $collection = null;
        }
        return $collection;
    }
}
