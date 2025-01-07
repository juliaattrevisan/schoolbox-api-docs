<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\RestApi;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\IPhotoDownloader;
use SensitiveParameter;

/**
 * Class PhotoDownloader
 *
 * Downloads each users full size picture from isams
 * Following the now used RestApi system
 *
 */
class PhotoDownloader implements IPhotoDownloader
{
    private RestApi $api;

    /**
     * @inheritDoc
     */
    public function __construct(string $host, #[SensitiveParameter] string $dsn, string $zipDir)
    {
        /* Store this locally just once
         */
        $credentials = (new DSN())->decode($dsn);
        $this->api = new RestApi($credentials['modules'][DSN::MODULE_RESTAPIHOST]['key'], $credentials['modules'][DSN::MODULE_RESTAPICLIENTID]['key'], $credentials['modules'][DSN::MODULE_RESTAPICLIENTSECRET]['key']);
    }

    /**
     * @inheritDoc
     */
    public function getPhoto(string $personId, string $roleType = self::ROLE_STUDENT, ?string $externalId = null): ?string
    {
        switch ($roleType) {
            case self::ROLE_STUDENT:
                $url = sprintf('Main/api/students/%s/photos/current', $externalId);
                return $this->api->request($url, 'GET', raw: true);
            case self::ROLE_STAFF:
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
