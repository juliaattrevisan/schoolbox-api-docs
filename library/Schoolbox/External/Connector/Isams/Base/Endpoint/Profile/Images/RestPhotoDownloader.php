<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use Schoolbox\Application\Exception\ApiException;
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
    private array $photoCache = [];
    private Api $api;

    /**
     * Constructor.
     *
     * @param array $credentials
     * @throws ApiException
     */
    public function __construct(#[SensitiveParameter] array $credentials)
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
     * @inheritDoc
     */
    public function getPhoto(string $personId, string $roleType = Role::STUDENT, ?string $externalId = null): ?string
    {
        switch ($roleType) {
            case Role::STUDENT:
                if (!isset($this->photoCache[$externalId])) {
                    // fetch the latest portrait
                    $url = sprintf('Main/api/students/%s/photos/current', $externalId);
                } else {
                    // fetch a specific portrait
                    $url = sprintf('Main/api/students/%s/photos/%s', $externalId, $this->photoCache[$externalId]);
                }
                return $this->api->request($url, 'GET', raw: true);

            case Role::STAFF:
                if (!isset($this->photoCache[$externalId])) {
                    // fetch the latest portrait
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/current', $externalId);
                } else {
                    // fetch a specific portrait
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/%s', 1176, 916);
                }
                return $this->api->request($url, 'GET', raw: true);

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
     * @inheritDoc
     */
    public function getLatestPhotoID(string $roleType = Role::STUDENT, string|int|null $externalId = null): ?int
    {
        switch ($roleType) {
            case Role::STUDENT:
                $url = sprintf('Main/api/students/%s', $externalId);
                $userObj =  $this->api->request($url, 'GET');
                break;
            case Role::STAFF:
                /* The internal id is in "Id" and the value we use for external id is "PersonId".
                 * The id used here is the isams internal id, which is NOT the same as their external id.
                 */
                $url = sprintf('Main/api/humanresources/employees/%s', 1176);
                $userObj =  $this->api->request($url, 'GET');
                print_r($userObj);
                break;
            default:
                return null;
        }

        /**
         * ignore all user data other than the latestPhotoId
         * In time we may need / want to use the rest of this payload, but for now we can just leave it.
         */
        $this->photoCache[$externalId] = $userObj['latestPhotoId'];
        return $userObj['latestPhotoId'];
    }
}
