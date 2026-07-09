<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Edlink\Base\Endpoint\IApi;

/**
 * Class PeopleIds
 *
 * A shared Edlink UUID -> resolved external ID (SIS ID) lookup.
 *
 * Profile strategies populate this during import; the TimetableCalendar
 * strategy reads from it when resolving enrolment person_ids. When
 * only timetable sync is enabled, generate() will fetch the mapping
 * on demand from /people.
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint\Lookup
 */
class PeopleIds
{
    /**
     * Guard against infinite pagination
     */
    private const MAX_CALLS = 500;

    /**
     * Number of records per page when generating from scratch
     */
    private const NUM_PER_PAGE = 10000;

    /**
     * Preferred identifier types from the Edlink identifiers array, in priority order.
     * The first match wins. Falls back to the Edlink UUID if none found.
     */
    private const IDENTIFIER_PRIORITY = ['sis_id', 'student_id', 'staff_id'];

    /**
     * Map of Edlink UUID => resolved external ID
     *
     * @var array<string, string>
     */
    private array $map = [];

    /**
     * Whether this lookup has been generated via /people
     */
    private bool $generated = false;

    /**
     * Record a mapping (called by Profile during import)
     */
    public function set(string $edlinkId, string $externalId): void
    {
        if ('' === $edlinkId || '' === $externalId) {
            return;
        }

        $this->map[$edlinkId] = $externalId;
    }

    /**
     * Resolve an Edlink UUID to its external ID.
     * Falls back to the Edlink UUID if no mapping exists.
     */
    public function resolve(string $edlinkId): string
    {
        return $this->map[$edlinkId] ?? $edlinkId;
    }

    /**
     * Populate the map from /people if it hasn't been filled already.
     * Used by the Classes strategy when profile sync is disabled.
     */
    public function generate(IApi $api): void
    {
        if ($this->generated || \count($this->map)) {
            return;
        }

        $nextUrl = sprintf('people?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $person) {
                $edlinkId = trim((string)($person['id'] ?? ''));

                if ('' === $edlinkId) {
                    continue;
                }

                $this->set($edlinkId, self::resolveExternalId($person));
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->generated = true;
    }

    /**
     * Resolve the best external ID for a person from Edlink data.
     *
     * Checks the identifiers[] array for SIS-provided IDs (sis_id, student_id,
     * staff_id) in priority order. Falls back to the first available identifier
     * or, finally, the Edlink UUID.
     */
    public static function resolveExternalId(array $person): string
    {
        $edlinkId = trim((string)($person['id'] ?? ''));
        $identifiers = $person['identifiers'] ?? [];

        if (\is_array($identifiers) && \count($identifiers)) {
            $idLookup = [];

            foreach ($identifiers as $identifier) {
                if (!\is_array($identifier)) {
                    continue;
                }

                $type = trim((string)($identifier['type'] ?? ''));
                $value = trim((string)($identifier['value'] ?? ''));

                if ('' !== $type && '' !== $value) {
                    $idLookup[$type] = $value;
                }
            }

            foreach (self::IDENTIFIER_PRIORITY as $preferredType) {
                if (isset($idLookup[$preferredType])) {
                    return $idLookup[$preferredType];
                }
            }

            /* Catch provider-specific types (canvas_id, powerschool_id, etc.)
             */
            if (\count($idLookup)) {
                return $idLookup[array_key_first($idLookup)];
            }
        }

        return $edlinkId;
    }

    /**
     * Resolve a single identifier value by type from the identifiers[] array.
     */
    public static function resolveIdentifierByType(array $person, string $type): string
    {
        $identifiers = $person['identifiers'] ?? [];

        if (!\is_array($identifiers)) {
            return '';
        }

        foreach ($identifiers as $identifier) {
            if (!\is_array($identifier)) {
                continue;
            }

            $idType = trim((string)($identifier['type'] ?? ''));
            $value = trim((string)($identifier['value'] ?? ''));

            if ($idType === $type && '' !== $value) {
                return $value;
            }
        }

        return '';
    }
}
