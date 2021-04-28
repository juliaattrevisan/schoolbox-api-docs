<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Payload;

use Schoolbox\User;


/**
 * Class StudentPhoto
 *
 * This payload is specifically used to obtain pictures of a particular student
 * by sending Geosnapshot a sample upon which to perform their AI matching
 *
 * The user should have a portrait picture, otherwise this can't work
 *
 * @package Schoolbox\Geosnapshot\Payload
 */
class StudentPhoto
{

    /**
     * Builds a payload for getting pictures with $viewee's face in them
     *
     * @param User $viewee
     * @return array
     * @throws \RuntimeException
     */
    public function build(User $viewee): array
    {
        if (!$viewee->getPortrait()) {
            throw new \RuntimeException(sprintf(
                'Viewee %s does not have a portrait picture for Geosnapshot.',
                $viewee->getFullname()
            ));
        }

        return [
            'face_image' => base64_encode($viewee->getPortrait()->read())
        ];
    }
}