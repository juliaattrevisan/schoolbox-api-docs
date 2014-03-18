<?php
/**
 * Helper methods for working with JSON output.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress;
use JsonSerializable;
use Traversable;

class Json {

    /**
     * Recursively applies jsonSerialize() to any JsonSerializable objects
     * within $var.
     *
     * @todo remove once PHP >= 5.4
     *
     * @param  mixed $var
     * @return mixed
     */
    public static function applyJsonSerializable($var) {
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            // PHP 5.4+ automatically applies jsonSerialize
            return $var;
        }
        elseif (is_array($var) || $var instanceof Traversable) {
            // Iterable: apply this method to all values in the collection
            $newVar = array();
            foreach ($var as $key => $value) {
                $newVar[$key] = self::applyJsonSerializable($value);
            }
            return $newVar;
        }
        elseif ($var instanceof JsonSerializable) {
            // Get serialized version if $var implements that interface, and
            // apply this method again in case it returns an iterable.
            return self::applyJsonSerializable($var->jsonSerialize());
        }
        return $var;
    }

}
