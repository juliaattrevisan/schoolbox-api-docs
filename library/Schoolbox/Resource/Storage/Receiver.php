<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

/**
 * Interface Receiver
 *
 * Implementing classes are capable of storing a temporary file permanently.
 * In reality, this is probably only going to be the StorageMapper,
 * but it's much better to rely on an interface for specific functionality.
 *
 * @package Schoolbox\Resource\Storage
 */
interface Receiver
{

    /**
     * Accept a new file upload
     *
     * @param string $basename
     * @param string $tempLocation
     * @param bool   $public
     * @return Model
     */
    public function accept($basename, $tempLocation, $public = false);

    /**
     * Accept a datastream upload
     *
     * @param string   $basename
     * @param resource $fp
     * @param bool     $public
     * @return Model
     */
    public function acceptStream($basename, $fp, $public = false);
}
