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
    public const ROLE_STUDENT = 'student';
    public const ROLE_STAFF = 'staff';

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
     * Download student photos
     *
     * @return void
     * @throws \RuntimeException
     */
    public function downloadStudentPhotos(): void;


    /**
     * Download staff photos
     *
     * @return void
     * @throws \RuntimeException
     */
    public function downloadStaffPhotos(): void;

    /**
     * Get a photo's contents after decide if $personId has a picture
     * Returns null if the file doesn't exist
     *
     *
     * @param string $personId
     * @param string $roleType should be one of the ROLE_* constants
     * @param null|string $externalId
     * @return string|null
     */
    public function getPhoto(string $personId, string $roleType = self::ROLE_STUDENT, ?string $externalId = null): ?string;

    /**
     * Removes all the photo-related files from the isams cache dir
     *
     * @return void
     */
    public function cleanup(): void;

    public function whichSystem(): string;
}
