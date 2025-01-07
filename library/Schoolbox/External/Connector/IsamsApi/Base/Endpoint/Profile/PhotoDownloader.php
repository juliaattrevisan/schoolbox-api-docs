<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\IsamsApi\Base\Endpoint\Profile;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\IPhotoDownloader;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\IsamsApi\Base\Endpoint\Batchapi;
use SensitiveParameter;

/**
 * Class PhotoDownloader
 *
 * Downloads a zip file containing profile pictures, from isams
 * For some reason the Guzzle implementation results in isams
 * stopping you:
 *
 * "iSAMS blocked the request as it came from an unknown source."
 *
 * The Alaress curl url lets you in though.
 */
class PhotoDownloader implements IPhotoDownloader
{
    private Batchapi $Api;

    /**
     * @inheritDoc
     */
    public function __construct(string $host, #[SensitiveParameter] string $dsn, string $zipDir)
    {
        /* Store this locally just once
         */
        $credentials = (new DSN())->decode($dsn);
        $this->Api = new Batchapi($credentials['modules'][DSN::MODULE_BATCHAPIHOST]['key'], $credentials['modules'][DSN::MODULE_BATCHAPICLIENTID]['key'], $credentials['modules'][DSN::MODULE_BATCHAPICLIENTSECRET]['key']);
    }

    /**
     * @inheritDoc
     */
    public function getPhoto(string $personId, string $roleType = self::ROLE_STUDENT, ?string $externalId = null): ?string
    {
        switch ($roleType) {
            case self::ROLE_STUDENT:
                $url = sprintf('Main/api/students/%s/photos/current', $externalId);
                return $this->Api->request($url, 'GET', raw: true);
            case self::ROLE_STAFF:
                $url = sprintf('Main/api/humanresources/employees/%s/photos/current', $externalId);
                return $this->Api->request($url, 'GET', raw: true);
            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function downloadStudentPhotos(): void
    {
        // unused for batchapi
    }

    /**
     * @inheritDoc
     */
    public function downloadStaffPhotos(): void
    {
        // unused for batchapi
    }

    /**
     * @inheritDoc
     */
    public function cleanup(): void
    {
        // unused for batchapi
    }
    public function whichSystem(): string
    {
        return 'batchAPI';
    }
}
