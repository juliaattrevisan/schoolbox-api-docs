<?php

/**
 * Output handling class that renders JSON.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;
use Alaress\Json as JsonHelper;

class Json extends Base
{
    /**
     * Ensures Content-Type is set to application/json, as it should be for JSON
     */
    public function preprocessOutput()
    {
        $this->getResponse()->headers->set('Content-Type', 'application/json');
    }

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with a "data" key.
     */
    protected function outputResponseBody()
    {
        echo json_encode($this->getData());
    }
}
