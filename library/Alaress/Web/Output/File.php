<?php
/**
 * Output handling class that outputs a file, either from the contents of a
 * variable or directly from the filesystem.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;

class File extends Base {

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with a "file" key, and that
     * appropriate file type headers have been set.
     */
    protected function outputResponseBody() {
        $response = $this->getResponse()->content->get();
        if ($response['file']) {
            readfile($response['file']);
        }
        else {
            echo $response['data'];
        }
    }

}
