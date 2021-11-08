<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Payload;

/**
 * Class RedactedPhoto
 *
 * Take the arbitrary array from External_User getPhotoRedactionList() and
 * manhandle it into an equally arbitrary and undocumented Geosnapshot structure
 */
class RedactedPhotoList
{

    /**
     * Forge the list into whatever Geosnapshot was expecting, divesting all connectors
     * of that responsibility by doing it here
     *
     * @param array $redactedPhotoList
     * @return array
     */
    public function build(array $redactedPhotoList): array
    {
        $stack = [];

        foreach ($redactedPhotoList as $redactedPhoto) {
            $stack[] = [
                'ext_id' => $redactedPhoto['external_id'],
                'redact_photo' => '',
                'redact_photo_modified_at' => '',
                'redacted' => (bool)$redactedPhoto['photo_redacted'],
                'attributes' => [
                    'year' => $redactedPhoto['yearLevel'],
                    'house' => $redactedPhoto['house'],
                    'profile' => '',
                ]
            ];
        }

        return $stack;
    }
}