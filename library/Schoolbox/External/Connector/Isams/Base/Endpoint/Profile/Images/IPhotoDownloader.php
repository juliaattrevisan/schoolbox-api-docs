<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

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
     * Get the latest photo ID for the user from the rest API
     *
     * @param string $roleType
     * @param string|int|null $identifier
     * @return int|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     */
    public function getLatestPhotoId(string $roleType, string|int|null $identifier): ?int;
}
