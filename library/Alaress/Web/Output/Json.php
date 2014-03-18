<?php
/**
 * Output handling class that renders JSON.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;
use JsonSerializable;

class Json extends Base {

    /**
     * Ensures Content-Type is set to application/json, as it should be for JSON
     */
    public function preprocessOutput() {
        $this->getResponse()->headers->set('Content-Type', 'application/json');
    }

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with a "data" key.
     */
    protected function outputResponseBody() {
        $response = $this->getResponse()->content->get();
        echo json_encode($this->applyJsonSerializable($response['data']));
    }

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
            return $var;
        }
        if ($var instanceof JsonSerializable) {
            $var = $var->jsonSerialize();
        }
        elseif (is_array($var) || $var instanceof Traversable) {
            foreach ($var as &$value) {
                $value = self::applyJsonSerializable($value);
            }
            unset($value);
        }
        return $var;
    }

}
