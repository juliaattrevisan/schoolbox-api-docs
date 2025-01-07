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
    public function __construct(string $host, #[SensitiveParameter] string $dsn, string $zipDir, string $zipDir)
    {
        /* Store this locally just once
         */
        $credentials = (new DSN())->decode($dsn);
        $this->Api = new Batchapi($credentials[DSN::MODULE_BATCHAPIHOST], $credentials[DSN::MODULE_BATCHAPICLIENTID], DSN::MODULE_BATCHAPICLIENTSECRET);
    }

    /**
     * @inheritDoc
     */
    public function getPhoto(string $personId): ?string
    {
        return null;
    }
}
