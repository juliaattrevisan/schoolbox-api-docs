<?php
/**
 * JsonSerializable interface for PHP 5.3
 *
 * Obviously doesn't tie into json_encode() like the 5.4 implementation, but we
 * can at least test for whether a class implements this interface.
 *
 * @todo      Remove when min supported PHP version >= 5.4
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

if (!interface_exists('JsonSerializable')) {
    interface JsonSerializable {
        /**
         * Specify data which should be serialized to JSON
         * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
         * @return mixed data which can be serialized by <b>json_encode</b>,
         * which is a value of any type other than a resource.
         */
        public function jsonSerialize();
    }
}
