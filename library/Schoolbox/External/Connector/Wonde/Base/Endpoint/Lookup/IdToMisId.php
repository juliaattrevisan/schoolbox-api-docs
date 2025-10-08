<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Wonde\Base\Endpoint\IApi;

/**
 * Generates a list of (id, mis_id) tuples which correspond to
 * (wonde internal identifier, sis external identifier) tuples
 */
class IdToMisId
{
    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


    /**
     * Number of records per page
     * The maximum is an unwritten 200 or so, apparently
     */
    private const NUM_PER_PAGE = 200;

    /**
     * Cache of user profiles indexed by external_id
     * both students and staff will be stored here
     * students first, then staff, so staff can override students (if they have the same external_id)
     * @var array
     */
    private array $userLookup = [];

    /**
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }


    /**
     * Generates a list of (id, mis_id) tuples which correspond to
     * (wonde internal identifier, sis external identifier) tuples
     *
     * @return array
     */
    public function generate(): array
    {
        $this->generateStudentLookup();
        $this->generateStaffLookup();
        return $this->userLookup;
    }


    /**
     * Tuples for students' identifiers
     *
     * @return void
     */
    private function generateStudentLookup(): void
    {
        $nextUrl = sprintf(
            'students?page=%s&per_page=%s',
            1,
            self::NUM_PER_PAGE
        );

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $students = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($students['data'] as $student) {
                /* Wonde internal identifier that is used throughout their api
                 */
                $internalIdentifier = trim((string)$student['id']);

                /* This is the actual sis' external id that wonde misrepresents
                 */
                $misId = trim((string)$student['mis_id']);

                /* Build a lookup progressively
                 */
                $this->userLookup[$internalIdentifier] = $misId;
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($students['meta']['pagination'])) {
                $nextUrl = $students['meta']['pagination']['next'] ?? false;
            }
        }
    }


    /**
     * Tuples for staffs' identifiers
     *
     * @return void
     */
    private function generateStaffLookup(): void
    {
        $nextUrl = sprintf(
            'employees?page=%s&per_page=%s',
            1,
            self::NUM_PER_PAGE
        );

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $staffs = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($staffs['data'] as $staff) {
                /* Wonde internal identifier that is used throughout their api
                 */
                $internalIdentifier = trim((string)$staff['id']);

                /* This is the actual sis' external id that wonde misrepresents
                 */
                $misId = trim((string)$staff['mis_id']);

                /* Build a lookup progressively
                 */
                $this->userLookup[$internalIdentifier] = $misId;
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($staffs['meta']['pagination'])) {
                $nextUrl = $staffs['meta']['pagination']['next'] ?? false;
            }
        }
    }
}
