<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile;

use Psr\SimpleCache\CacheInterface;
use Schoolbox\Url\Uri;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use SensitiveParameter;
use ZipArchive;

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
interface IPhotoDownloader
{
    /**
     * Constructor.
     *
     * @param string $host
     * @param string $dsn
     * @param string $zipDir
     * @throws \DomainException
     */
    public function __construct(string $host, #[SensitiveParameter] string $dsn, string $zipDir);

    /**
     * Get a photo's contents after decide if $personId has a picture
     * Returns null if the file doesn't exist
     *
     *
     * @param string $personId
     * @return string|null
     */
    public function getPhoto(string $personId): ?string;
}
