<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\ProfileImages;

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
     * @throws \DomainException
     */
    public function __construct(#[SensitiveParameter] array $credentials)
    {
        /* Store this locally just once
         */
        $this->api = new Api(
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPIHOST]['key'] ?? '')),
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPICLIENTID]['key'] ?? '')),
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPICLIENTSECRET]['key'] ?? ''))
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
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/%s', $externalId, $this->photoCache[$externalId]);
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
    public function cleanup(): void
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
                $url = sprintf('Main/api/humanresources/employees/%s', $externalId);
                $userObj =  $this->api->request($url, 'GET');
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
