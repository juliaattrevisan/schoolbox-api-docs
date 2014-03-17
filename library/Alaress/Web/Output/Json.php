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

        // Use patched JsonSerializable interface.
        // TODO: remove once PHP >= 5.4
        $output = array();
        foreach ($response['data'] as $key => $value) {
            $output[$key] = ($value instanceof JsonSerializable) ? $value->jsonSerialize() : $value;
        }
        echo json_encode($output);
    }

}
