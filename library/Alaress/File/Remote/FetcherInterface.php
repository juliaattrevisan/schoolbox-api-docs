<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\File\Remote;

use OutOfBoundsException;

interface FetcherInterface
{

    /**
     * Retrieves the metadata for the given fileId
     *
     * @param int $fileId
     * @return array
     */
    public function fetchMetaData($fileId);


    /**
     * Attempts to retrieve remote source to local destination, returns the path of the saved file.
     *
     * @param int $fileId
     * @throws OutOfBoundsException
     * @return string
     */
    public function fetchToFile($fileId);
}
