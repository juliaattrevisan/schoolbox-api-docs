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
            /* Figure out which querystring param to use based on data set,
             * since live connectors won't come with a user.id we have to use
             * the external_id variant instead
             */
            if ('' === (string)$redactedPhoto['id']) {
                $redactPhotoPath = sprintf(
                    '%s/geosnapshot/portrait.php?userId=%s',
                    $this->fqdn,
                    $redactedPhoto['external_id']
                );

                /* Can't obtain this without an accompanying user id
                 * We can't guarantee user accounts were created for this, though;
                 * customised UPS rulesets might preclude their accounts being spawned
                 */
                $profilePhotoPath = '';
            } else {
                $redactPhotoPath = sprintf(
                    '%s/geosnapshot/portrait.php?id=%s',
                    $this->fqdn,
                    $redactedPhoto['id']
                );

                $profilePhotoPath = sprintf(
                    '%s/search/user/%s',
                    $this->fqdn,
                    $redactedPhoto['id']
                );
            }

            /* Calculate a modified date, where one might not already exist
             * redacted_at might be null when the user doesn't have a picture in the sis
             */
            $redactedAt = '';

            if ('' !== (string)$redactedPhoto['redact_photo_modified_at']) {
                $redactedAt = gmdate("Y-m-d\TH:i:s\Z", strtotime($redactedPhoto['redact_photo_modified_at']));
            }

            /* Manufacture Geosnapshot-compatible format
             * Dedupe anything that might have been duped in connector land due to joins etc.
             */
            if (!isset($stack[$redactedPhoto['external_id']])) {
                $stack[$redactedPhoto['external_id']] = [
                    'ext_id' => $redactedPhoto['external_id'],
                    'redact_photo' => $redactPhotoPath,
                    'redact_photo_modified_at' => $redactedAt,
                    'redacted' => (bool)$redactedPhoto['photo_redacted'],
                    'attributes' => [
                        'year' => $redactedPhoto['yearLevel'],
                        'house' => $redactedPhoto['house'],
                        'profile' => $profilePhotoPath,
                    ]
                ];
            }
        }

        return array_values($stack);
    }
}
