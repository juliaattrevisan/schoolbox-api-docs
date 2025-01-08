<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\ProfileImages;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Rest\RestApi;
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
    private RestApi $api;

    /**
     * Constructor.
     *
     * @param string $dsn
     * @throws \DomainException
     */
    public function __construct(#[SensitiveParameter] string $dsn)
    {
        /* Store this locally just once
         */
        $credentials = (new DSN())->decode($dsn);
        $this->api = new RestApi($credentials['modules'][DSN::MODULE_RESTAPIHOST]['key'], $credentials['modules'][DSN::MODULE_RESTAPICLIENTID]['key'], $credentials['modules'][DSN::MODULE_RESTAPICLIENTSECRET]['key']);
    }

    /**
     * @inheritDoc
     */
    public function getPhoto(string $personId, string $roleType = Role::STUDENT, ?string $externalId = null): ?string
    {
        switch ($roleType) {
            case Role::STUDENT:
                $url = sprintf('Main/api/students/%s/photos/current', $externalId);
                return $this->api->request($url, 'GET', raw: true);
            case Role::STAFF:
                $url = sprintf('Main/api/humanresources/employees/%s/photos/current', $externalId);
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
    public function whichSystem(): string
    {
        return 'restAPI';
    }
}
