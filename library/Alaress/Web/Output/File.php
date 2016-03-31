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

class File extends Base
{
    /**
     * A file path to output.
     *
     * @todo make this support Flysystem
     *
     * @var string
     */
    protected $file = null;

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with a "file" key, and that
     * appropriate file type headers have been set.
     */
    protected function outputResponseBody()
    {
        $file = $this->getFile();
        $file = isset($file) ? $file : $this->retrieveFieldFromResponse('file');
        if ($file) {
            readfile($file);
        } else {
            $data = $this->getData();
            if (isset($data)) {
                echo $data;
            } else {
                echo $this->retrieveFieldFromResponse('data');
            }
        }
    }

    /**
     * @return string
     */
    protected function getFile()
    {
        return $this->file;
    }

    /**
     * @param  string $file
     * @return $this
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }
}
