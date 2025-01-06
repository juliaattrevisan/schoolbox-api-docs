<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Payload;

use League\Flysystem\FilesystemException;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\User;

/**
 * Class Photo
 *
 * This payload is specifically used to obtain pictures of a particular person
 * by sending Geosnapshot a sample upon which to perform their AI matching
 *
 * The user should have a portrait picture, otherwise this can't work
 *
 * @package Schoolbox\Geosnapshot\Payload
 */
class Photo
{
    /**
     * Constructor
     *
     * @param FilesystemProxy $filesystemProxy
     */
    public function __construct(private FilesystemProxy $filesystemProxy)
    {
    }


    /**
     * Builds a payload for getting pictures with $viewee's face in them
     *
     * @param User $viewee
     * @param User $viewer
     * @return array
     * @throws FilesystemException
     * @throws \RuntimeException
     */
    public function build(User $viewee, User $viewer): array
    {
        if (!$viewee->getPortrait()) {
            throw new \RuntimeException(sprintf(
                'Viewee %s does not have a portrait picture for Geosnapshot.',
                $viewee->getFullname()
            ));
        }

        /* TODO consolidate ext_user_id into a codec, for consistent representation
         */
        return [
            'face_image' => base64_encode($this->filesystemProxy->read($viewee->getPortrait())),
            'ext_user_id' => INSTANCE_ID . '.' . $viewer->getId(),
        ];
    }
}
