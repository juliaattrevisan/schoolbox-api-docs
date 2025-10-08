<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use GuzzleHttp\Exception\GuzzleException;
use Random\RandomException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Rest\Api;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\Role;
use SensitiveParameter;

/**
 * Class PhotoDownloader
 *
 * Downloads each users full size picture from isams
 * Following the now used RestApi system
 *
 */
class RestPhotoDownloader implements IPhotoDownloader
{
    /**
     * A set of (isams internal identifier, sis photo id) tuples that remember when we fetched
     * a picture for someone; if they never had a sis photo id, we'll get the latest one.
     * Otherwise we'll use the one specified there.
     *
     * @var array
     */
    private array $photoCache = [];


    /**
     * @var Api
     */
    private Api $api;


    /**
     * Constructor.
     *
     * @param array $credentials
     * @param LoggerInterface $log
     * @throws GuzzleException
     * @throws RandomException
     */
    public function __construct(#[SensitiveParameter] array $credentials, private LoggerInterface $log)
    {
        /* Store this locally just once
         */
        $this->api = new Api(
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPI]['host'] ?? '')),
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPI]['clientid'] ?? '')),
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPI]['clientsecret'] ?? ''))
        );
    }


    /**
     * This uses the roletype, internal identifier and sis photo identifier to construct a url
     * to obtain a photo from the rest api.
     *
     * Ths sis photo identifier is stored in sis_user_profile.metadata and used to decide when
     * the school's updated a picture in isams: it'll be different id in that circumstance.
     * Also, we need to use that in a url for an api request to obtain the picture's binary data.
     * @param array $data
     * @return string|null
     * @throws GuzzleException
     * @throws RandomException
     */
    public function getPhoto(array $data): ?string
    {
        $roleType = $data['type'];
        $sisPhotoId = $data['sisPhotoId'];

        /* REST student photos require usage of SchoolId in the urls, which also happens to be
         * what we assumed for a student's external id. Staff require usage of isams internal id
         */
        if (Role::STUDENT === $roleType) {
            $identifier = $data['externalId'];
        } else {
            $identifier = $data['internalId'];
        }

        switch ($roleType) {
            case Role::STUDENT:
                if (!isset($this->photoCache[$identifier]) || null === $sisPhotoId) {
                    // fetch the latest portrait
                    $url = sprintf('Main/api/students/%s/photos/current', $identifier);

                    $this->log->debug(sprintf(
                        'Getting the current %s photo from %s',
                        $roleType,
                        $url
                    ));
                } else {
                    // fetch a specific portrait
                    $url = sprintf('Main/api/students/%s/photos/%s', $identifier, $sisPhotoId);

                    $this->log->debug(sprintf(
                        'Getting a specific %s photo from %s',
                        $roleType,
                        $url
                    ));
                }
                return $this->api->request(path: $url, method: 'GET', raw: true);

            case Role::STAFF:
                if (!isset($this->photoCache[$identifier]) || null === $sisPhotoId) {
                    // fetch the latest portrait
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/current', $identifier);

                    $this->log->debug(sprintf(
                        'Getting the current %s photo from %s',
                        $roleType,
                        $url
                    ));
                } else {
                    // fetch a specific portrait
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/%s', $identifier, $sisPhotoId);

                    $this->log->debug(sprintf(
                        'Getting a specific %s photo from %s',
                        $roleType,
                        $url
                    ));
                }
                return $this->api->request(path: $url, method: 'GET', raw: true);

            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function downloadStudentPhotos(): void
    {
        // unused for RestApi
    }

    /**
     * @inheritDoc
     */
    public function downloadStaffPhotos(): void
    {
        // unused for RestApi
    }


    /**
     * $identifier is the isams internal identifier that they need for the rest api urls.
     *
     * The internal id is in "Id" and the value we use for external id is "PersonId".
     * The identifier used here is the isams internal id, which is NOT the same as their external id.
     *
     * @inheritDoc
     * @throws RandomException
     */
    public function getLatestPhotoId(array $data): ?int
    {
        $roleType = $data['type'];

        /* REST student photos require usage of SchoolId in the urls, which also happens to be
         * what we assumed for a student's external id. Staff require usage of isams internal id
         */
        if (Role::STUDENT === $roleType) {
            $identifier = $data['externalId'];
        } else {
            $identifier = $data['internalId'];
        }

        switch ($roleType) {
            case Role::STUDENT:
                $url = sprintf('Main/api/students/%s', $identifier);

                $this->log->debug(sprintf(
                    'Looking for the latest student profile picture identifier at %s',
                    $url
                ));

                $userObj =  $this->api->request($url, 'GET');
                break;
            case Role::STAFF:
                $url = sprintf('Main/api/humanresources/employees/%s', $identifier);

                $this->log->debug(sprintf(
                    'Looking for the latest staff profile picture identifier at %s',
                    $url
                ));

                $userObj = $this->api->request($url, 'GET');
                break;
            default:
                return null;
        }

        /* Note that $userObj['latestPhotoId'] might actually be null in isams land.
         */

        /**
         * ignore all user data other than the latestPhotoId
         * In time we may need / want to use the rest of this payload, but for now we can just leave it.
         */
        $this->photoCache[$identifier] = $userObj['latestPhotoId'];

        return $userObj['latestPhotoId'];
    }


    /**
     * Only process new pictures if its identifier exists in isams: we can't process pictures
     * that isams never had
     *
     * @param string $externalId
     * @param array $data
     * @param int|null $newSisPhotoId
     * @return bool
     */
    public function needsUpdating(string $externalId, array $data, ?int $newSisPhotoId): bool
    {
        $this->log->debug(sprintf(
            'Comparing old sis photo id %s to new sis photo id %s',
            $data['person']['sisPhotoId'] ?? '(null)',
            $newSisPhotoId ?? '(null)',
        ));

        /* isams doesn't know the new id: probably because this person has never had a picture.
         */
        if (null === $newSisPhotoId) {
            $this->log->info(sprintf(
                '%s %s (%s) has a null latest photo identifier; probably no picture exists. Skipping.',
                $data['person']['firstname'],
                $data['person']['lastname'],
                $externalId
            ));

            return false;
        }

        /* Old id is the same as the new id: no changes detected
         *
         * Do a string comparison to avoid json_decoded data from mismatching due to data type
         * even though we probably could have made newSisPhotoId always come out as a string from
         * the method that fetches it; not sure if we'd have needed it for something else later.
         */
        if ((string)$data['person']['sisPhotoId'] === (string)$newSisPhotoId) {
            $this->log->info(sprintf(
                '%s %s (%s) sis photo id %s is the same as latest: %s. Picture is the same. Skipping.',
                $data['person']['firstname'],
                $data['person']['lastname'],
                $data['person']['sisPhotoId'],
                $newSisPhotoId,
                $externalId
            ));

            return false;
        }

        return true;
    }
}
