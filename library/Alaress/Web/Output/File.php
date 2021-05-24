<?php

/**
 * Output handling class that outputs a file, either from the contents of a
 * variable or directly from the filesystem.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        if ($file) {
            readfile($file);
        } else {
            echo $this->getData();
        }
    }

    /**
     * @return string
     */
    protected function getFile()
    {
        return isset($this->file)
            ? $this->file
            : $this->retrieveFieldFromResponse('file');
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
