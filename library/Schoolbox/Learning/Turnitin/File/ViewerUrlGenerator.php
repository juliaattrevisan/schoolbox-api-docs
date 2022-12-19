<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\File;

use Schoolbox\Learning\Turnitin\Api as TurnitinApi;
use Schoolbox\User;

/**
 * Generate a viewer URL for a given Turnitin report.
 */
class ViewerUrlGenerator
{
    /* A specially-hardcoded value to have programmatically accepted the eula
     * It's part of our T&C with turnitin
     */
    private const EULA_ACCEPTANCE_DATE = '2021-01-01T00:00:00.000Z';

    /**
     * Constructor
     *
     * @param TurnitinApi $api Turnitin API
     */
    public function __construct(private TurnitinApi $api)
    {
    }

    /**
     * Generate the URL via which the current user may view a TII report.
     *
     * @param Model  $turnitin            The TII report model.
     * @param User   $viewer              The user viewing the report.
     * @param string $viewerPermissionSet The permission set: one of the
     *                                    `Process\Report::VIEWER_PERMISSION_SET_*`
     *                                    constants
     * @param bool   $allowSaveChanges    May the user save changes to the
     *                                    report?
     * @return array{viewer_url: string}|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     */
    public function generateViewerUrl(
        Model $turnitin,
        User $viewer,
        string $viewerPermissionSet,
        bool $allowSaveChanges,
    ): ?array {
        $api = $this->api;

        /* Choose a viewer permission set based on your role
         * Staff members get a different set than students or parents
         *
         * Start with the lowest-permission settings
         */
        $payload = [
            'viewer_user_id'                => $viewer->getId(),
            'locale'                        => 'en',
            'viewer_default_permission_set' => $viewerPermissionSet,
            'eula'                          => [
                'version'            => 'v1beta',
                'accepted_timestamp' => self::EULA_ACCEPTANCE_DATE,
                'language'           => 'en-US',
            ],
        ];

        /* Then decide if you're a staff and you need more stuff
         */
        if ($allowSaveChanges) {
            $payload = array_merge(
                $payload,
                [
                    'similarity' => [
                        'view_settings' => [
                            'save_changes' => true,
                        ],
                    ],
                ]
            );
        }

        /* TODO Apparently localisation is a thing, set via locale tuple;
         *  but I have assumed en for now
         */
        return $api->retrieve(
            'POST',
            "/api/v1/submissions/{$turnitin->getTurnitinSubmissionId()}/viewer-url",
            $payload,
        );
    }
}
