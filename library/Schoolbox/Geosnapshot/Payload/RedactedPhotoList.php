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
     * @var string
     */
    private string $fqdn;


    /**
     * @param string $fqdn
     */
    public function __construct(string $fqdn)
    {
        $this->fqdn = $fqdn;
    }


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
            /* Path to portrait picture and search page - what's this for though?
             */
            $redactPhotoPath = sprintf(
                'https:%s/portrait.php?id=%s&size=constrain200',
                $this->fqdn,
                $redactedPhoto['id']
            );

            $profilePhotoPath = sprintf(
                'https:%s/search/user/%s',
                $this->fqdn,
                $redactedPhoto['id']
            );

            /* Manufacture Geosnapshot-compatible format
             * user.updated_at is the closest synonym to redact_photo_modified_at
             */
            $stack[] = [
                'ext_id' => $redactedPhoto['external_id'],
                'redact_photo' => $redactPhotoPath,
                'redact_photo_modified_at' => gmdate("Y-m-d\TH:i:s\Z", strtotime($redactedPhoto['updated_at'])),
                'redacted' => (bool)$redactedPhoto['photo_redacted'],
                'attributes' => [
                    'year' => $redactedPhoto['yearLevel'],
                    'house' => $redactedPhoto['house'],
                    'profile' => $profilePhotoPath,
                ]
            ];
        }

        return $stack;
    }
}
