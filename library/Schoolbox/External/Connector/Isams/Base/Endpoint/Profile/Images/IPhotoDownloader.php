<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use GuzzleHttp\Exception\GuzzleException;

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
     * Each implementation of IPhotoDownloader requires different scalars to produce the binary
     * content of a profile picture. While this is conceptually synonymous, it is not syntactically
     * synonymous: especially since the REST API requires different ids depending on the user's
     * role type.
     *
     * @param array $data
     * @return string|null
     */
    public function getPhoto(array $data): ?string;


    /**
     * Get the latest photo ID for the user from the rest API
     *
     * @param array $data
     * @return int|null
     * @throws GuzzleException
     * @throws \JsonException
     */
    public function getLatestPhotoId(array $data): ?int;


    /**
     * Each implementation of IPhotoDownloader can figure out whether it needs to process its images,
     * and will do so in their own unique manner.
     *
     * @param string $externalId
     * @param array $data
     * @param int|null $newSisPhotoId
     * @return bool
     */
    public function needsUpdating(string $externalId, array $data, ?int $newSisPhotoId): bool;
}
