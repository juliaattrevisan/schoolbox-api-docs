<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

use Alaress_Date_Format;
use Schoolbox\User;

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
     * @param string                   $basename      The name to give the file in storage.
     * @param string                   $tempLocation  The location in temp storage where the file currently exists.
     * @param bool                     $public        Should the file be public in storage?
     * @param Alaress_Date_Format|null $expiryAt      When (if ever) should the file expire?
     * @param User|null                $uploadingUser The user uploading the file.
     * @return Model
     */
    public function accept(
        $basename,
        $tempLocation,
        $public = false,
        ?Alaress_Date_Format $expiryAt = null,
        ?User $uploadingUser = null,
    ): Model;

    /**
     * Accept a datastream upload
     *
     * @param string                   $basename      The name to give the file in storage.
     * @param resource|string          $fp            Either a pointer to a file, or its contents.
     * @param bool                     $public        Should the file be public in storage?
     * @param Alaress_Date_Format|null $expiryAt      When (if ever) should the file expire?
     * @param User|null                $uploadingUser The user uploading the file.
     * @return Model
     */
    public function acceptStream(
        $basename,
        $fp,
        $public = false,
        ?Alaress_Date_Format $expiryAt = null,
        ?User $uploadingUser = null,
    ): Model;
}
