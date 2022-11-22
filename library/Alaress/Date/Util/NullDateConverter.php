<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Date\Util;

use Alaress_Date_NullDate;
use ReflectionObject;

/**
 * Utility class for recursively iterating an object's properties, and replacing
 * any `Alaress_Date_NullDate` instances found with actual `null` values.
 *
 * (Currently unused, but could be used for mass-processing serialized objects
 * from a Redis queue and re-storing them without null dates.)
 */
class NullDateConverter
{
    /**
     * A collection of already-processed objects.
     *
     * Required because attempting to iterate an object's properties recursively
     * can result in recursive object references, which will otherwise cause an
     * infinite loop.
     *
     * @var array<string, object>
     */
    private array $objectMap = [];

    /**
     * The maximum number of nested objects deep into an object to convert.
     */
    private const MAX_DEPTH = 500;

    /**
     * Convert `$object`'s null dates into actual nulls.
     *
     * @param object $object
     * @return object
     */
    public function convert(object $object): object
    {
        return $this->convertNullDates($object, 0);
    }

    /**
     * Recursively convert `$object`'s null dates into actual null values.
     *
     * @param object $object
     * @param int    $currentDepth The current depth of this conversion: if
     *                             at or greater than `self::MAX_DEPTH`, does
     *                             not recurse any further.
     * @return object
     */
    private function convertNullDates(object $object, int $currentDepth): object
    {
        // Don't process the same object more than once
        $hash = spl_object_hash($object);
        if (isset($this->objectMap[$hash])) {
            return $this->objectMap[$hash];
        }
        $this->objectMap[$hash] = $object;

        // Now iterate the object's properties
        $reflection = new ReflectionObject($object);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $reflectionProperty->setAccessible(true);
            // Maybe this is being accessed before the property is initialized:
            // skip if so, as it has no value
            if ($reflectionProperty->isInitialized($object)) {
                $property = $reflectionProperty->getValue($object);

                // Convert null dates to nulls
                if ($property instanceof Alaress_Date_NullDate) {
                    $reflectionProperty->setValue($object, null);
                    continue;
                }

                // Eventually, this will need to convert incomplete null dates
                // to nulls
//                if ($property instanceof \__PHP_Incomplete_Class) {
//                    if ($property->__PHP_Incomplete_Class_Name === 'Alaress_Date_NullDate') {
//                        $reflectionProperty->setValue($object, null);
//                        continue;
//                    }
//                }

                // Any null dates within this object are converted recursively
                if (is_object($property)) {
                    // Don't process object if already at max depth
                    if ($currentDepth >= self::MAX_DEPTH) {
                        $property = $this->convertNullDates($property, $currentDepth + 1);
                        $reflectionProperty->setValue($object, $property);
                    }
                }
            }
        }
        return $object;
    }
}
