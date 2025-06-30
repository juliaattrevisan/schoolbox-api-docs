<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use Schoolbox\Role;

/**
 * Class IPhotoDownloader
 *
 * Downloads profile pictures from isams
 */
interface IPhotoDownloader
{
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
     * @param string $roleType should be one of the ROLE_* constants
     * @param string $identifier
     * @param string $sisPhotoId
     * @return string|null
     */
    public function getPhoto(string $roleType, string $identifier, int $sisPhotoId): ?string;


    /**
     * Get the latest photo ID for the user from the rest API
     *
     * @param string $roleType
     * @param string|int|null $identifier
     * @return int|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     */
    public function getLatestPhotoID(string $roleType, string|int|null $identifier): ?int;
}
