<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\User;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Tests\Support\ApiTester;
use Schoolbox\Role;

/**
 * user.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api ApiUserCest
 */
class ApiUserCest
{
    public function _before(ApiTester $i)
    {
    }

    /**
     * Get a list of assessments
     * @param ApiTester $i
     * @throws ServiceNotFound
     * @throws \Exception
     */
    public function testApiUserSearch(ApiTester $i): void
    {
        $users = $i->getUsers();

        foreach ($users as $user) {
            $i->switchUser($user);

            foreach ($this->apiUserSearchData($i) as $apiUserSearchData) {
                $data = [];
                if (array_key_exists('search', $apiUserSearchData)) {
                    $data['search'] = $apiUserSearchData['search'];
                }
                if (array_key_exists('limit', $apiUserSearchData)) {
                    $data['limit'] = $apiUserSearchData['limit'];
                }
                if (array_key_exists('cursor', $apiUserSearchData)) {
                    $data['cursor'] = $apiUserSearchData['cursor'];
                }
                if (array_key_exists('filter', $apiUserSearchData)) {
                    $data['filter'] = json_encode($apiUserSearchData['filter'], JSON_THROW_ON_ERROR);
                }

                $i->sendGet('/api/user', $data);
                if ($user->isSuperuser()) {
                    if ($apiUserSearchData['valid']) {
                        $i->seeResponseCodeIs(200);

                        $returnedCount = count($i->grabDataFromResponseByJsonPath('$.data')[0]);
                        $i->comment("{$returnedCount} retrieved.");
                        $totalCount = $i->grabDataFromResponseByJsonPath('$.metadata.count');
                        $i->comment("{$totalCount[0]} found.");

                        // Expect results by default for valid searches.  Supply false to expect no results.
                        if ($apiUserSearchData['expectResults'] ?? true) {
                            $i->assertGreaterThan(0, $returnedCount);
                            $i->assertGreaterThanOrEqual($returnedCount, $totalCount);
                        }
                    } else {
                        $i->seeResponseCodeIs(400);
                        $i->comment(implode('', $i->grabDataFromResponseByJsonPath('$.title')));
                    }
                } else {
                    $i->seeResponseCodeIs(401);
                }
            }
        }
    }

    /**
     * Test create user
     * @param ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testCreate(ApiTester $i): void
    {
        $users = $i->getUsers();

        foreach ($users as $user) {
            $i->switchUser($user);
            foreach ($this->createData($i) as $data) {
                $i->sendPost('/api/user', $data);
                if ($user->isSuperuser()) {
                    $i->seeResponseCodeIs(201);
                    $i->sendPatchAsJson('/api/user/22', ['yearLevel' => ['id' => null]]);
                } else {
                    $i->seeResponseCodeIs(401);
                }
            }
        }
    }

    /**
     * Data to test filter
     * @param ApiTester $i
     * @return Generator
     */
    private function apiUserSearchData(ApiTester $i): Generator
    {
        yield [
            'search' => 'reed',
            'valid'  => true,
        ];
        yield [
            'search' => null,
            'valid'  => true,
        ];
        yield [
            'cursor' => '12', // Low number to ensure results are returned.
            'valid'  => true,
        ];
        yield [
            'cursor' => 12, // Low number to ensure results are returned.  Allow integer as well as string even through string is specified in the API docs.
            'valid'  => true,
        ];
        yield [
            'cursor' => null,
            'valid'  => true,
        ];
        yield [
            'limit' => 'abcde',
            'valid'  => false,
        ];
        yield [
            'limit' => '2', // Allow digit string as well as integer even through integer is specified in the API docs.
            'valid'  => true,
        ];
        yield [
            'limit' => 3,
            'valid'  => true,
        ];
        yield [
            'limit' => null,
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'username' => 'jreed',
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'role' => [
                    'id' => $i->getRole(Role::STAFF)->getId(),
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'roleType' => Role::STAFF,
            ],
            'valid'  => true,
        ];
        yield [
            // Empty array filter.
            'filter' => [],
            'valid'  => true,
        ];
        yield [
            // Blank string filter.
            'filter' => '',
            'valid'  => false,
        ];
        yield [
            // Null filter.
            'filter' => null,
            'valid'  => false,
        ];
        yield [
            // Null string filter.
            'filter' => 'null',
            'valid'  => false,
        ];
        yield [
            'filter' => [
                // Year/role/campus in 'id' format.
                'year' => [
                    'id' => 3 // Year '9' from db table `lu_year_level`.
                ],
                'role' => [
                    'id' => '6' // Role 'Middle Student' from db table `groups` (supply one as a string - it should be treated the same as an integer).
                ],
                'campus' => [
                    'id' => 1 // Campus 'Secondary' from db table `luCampus`.
                ],
            ],
            'valid' => true,
        ];
        yield [
            'filter' => [
                // Year/role/campus in 'name' format.
                'year' => [
                    'name' => '9' // From db table `lu_year_level`.
                ],
                'role' => [
                    'name' => 'Middle Student' // From db table `groups`.
                ],
                'campus' => [
                    'name' => 'Secondary' // From db table `luCampus`.
                ],
            ],
            'valid' => true,
        ];
        yield [
            'filter' => [
                // Year/role/campus in mixed array format.
                'year' => [
                    ['id' => '3'], // Year '9' from db table `lu_year_level`.
                    ['name' => '9'], // From db table `lu_year_level`.
                ],
                'role' => [
                    ['id' => 6], // Role 'Middle Student' from db table `groups`.
                    ['name' => 'Middle Student'], // From db table `groups`.
                ],
                'campus' => [
                    ['id' => 2], // Campus 'Primary' from db table `luCampus`.
                    ['name' => 'Secondary'], // From db table `luCampus`.
                ],
            ],
            'valid' => false, // No support for arrays of years/roles/campuses.  Maybe in the future.
        ];
        yield [
            'filter' => [
                // Year/role/campus redundantly specified.
                'year' => [
                    'id' => 3, // Year '9' from db table `lu_year_level`.
                    'name' => '9', // From db table `lu_year_level`.
                ],
                'role' => [
                    'id' => 6, // Role 'Middle Student' from db table `groups`.
                    'name' => 'Middle Student', // From db table `groups`.
                ],
                'campus' => [
                    'id' => 1, // Campus 'Secondary' from db table `luCampus`.
                    'name' => 'Secondary', // From db table `luCampus`.
                ],
            ],
            'valid' => true,
        ];
        yield [
            'filter' => [
                // Year/role/campus redundantly specified in arrays.
                'year' => [
                    [
                        'id' => 3, // Year '9' from db table `lu_year_level`.
                        'name' => '9', // From db table `lu_year_level`.
                    ]
                ],
                'role' => [
                    [
                        'id' => 6, // Role 'Middle Student' from db table `groups`.
                        'name' => 'Middle Student', // From db table `groups`.
                    ]
                ],
                'campus' => [
                    [
                        'id' => 1, // Campus 'Secondary' from db table `luCampus`.
                        'name' => 'Secondary', // From db table `luCampus`.
                    ]
                ],
            ],
            'valid' => false, // No support for arrays of years/roles/campuses.  Maybe in the future.
        ];
        yield [
            'filter' => [
                // Extra keys in year/role/campus arrays.
                'year' => [
                    'id' => 3, // Year '9' from db table `lu_year_level`.
                    'name' => '9', // From db table `lu_year_level`.
                    'unexpectedKey' => 'unexpectedValue',
                ],
                'role' => [
                    'id' => 6, // Role 'Middle Student' from db table `groups`.
                    'name' => 'Middle Student', // From db table `groups`.
                    'unexpectedKey' => 'unexpectedValue',
                ],
                'campus' => [
                    'id' => 1, // Campus 'Secondary' from db table `luCampus`.
                    'name' => 'Secondary', // From db table `luCampus`.
                    'unexpectedKey' => 'unexpectedValue',
                ],
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Year/role/campus incorrectly specified.
                'year' => '1998',
                'role' => 'Middle Student',
                'campus' => 'Secondary',
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Year/role/campus incorrectly specified.
                'year' => 1998,
                'role' => 6,
                'campus' => 2,
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Year incorrectly specified.
                // Note: prior to 25.1, this would incorrectly return all results, because the filter would be ignored.
                'year' => [300, 900, 1200],
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Role incorrectly specified.
                // Note: prior to 25.1, this would incorrectly return all results, because the filter would be ignored.
                'role' => ['Non-existent role 1', 'Non-existent role 2', 'Non-existent role 3'],
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Campus incorrectly specified.
                // Note: prior to 25.1, this would incorrectly return all results, because the filter would be ignored.
                'campus' => ['Non-existent campus 1', 'Non-existent campus 2', 'Non-existent campus 3'],
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // String fields incorrectly specified as ints.
                // Note: prior to 25.1, this would cause no validation error, but would return no results.
                'externalId' => 12345,
                'username' => 12345,
                'email' => 12345,
                'roleType' => 12345,
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // String fields incorrectly specified as null.
                // Note: prior to 25.1, this would incorrectly return all results, because the filter would be ignored.
                'externalId' => null,
                'username' => null,
                'email' => null,
                'roleType' => null,
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Test externalId with a string value.
                // In sales data set, Jana Reed has externalId 507.
                'externalId' => '507',
            ],
            'valid' => true,
        ];
        yield [
            'filter' => [
                // Test externalId with an integer value.
                // In sales data set, Jana Reed has externalId 507.
                // Although externalId is specified in the API docs as string only, allow integer also for backwards compatibility.
                'externalId' => 507,
            ],
            'valid' => true,
        ];
        yield [
            'filter' => [
                // Non-existent roleType.
                // Note: prior to 25.1, this would cause no validation error, but would return no results.
                'roleType' => 'Non-existent role type',
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Unexpected key.
                // Note: prior to 25.1, this would incorrectly return all results, because the filter would be ignored.
                'unexpectedKey' => 'unexpectedValue',
            ],
            'valid' => false,
        ];
    }

    /**
     * Data to test create user
     * @param ApiTester $i
     * @return Generator
     */
    public function createData(ApiTester $i): Generator
    {
        yield [
            'username'  => $i->generateText(10, true),
            "role"      => [
                'id' => $i->getRole(Role::STAFF)->getId(),
            ],
            'campus'    => [
                ['id' => $i->getCampuses()->offsetGet(1)->getId()],
            ],
            'firstName' => $i->generateText(8, true),
        ];
        yield [
            'campus'           => [
                ['id' => $i->getCampuses()->offsetGet(0)->getId()],
                ['id' => $i->getCampuses()->offsetGet(1)->getId()],
            ],
            'role'             => [
                'id' => $i->getRole(Role::STUDENT)->getId(),
            ],
            'schoolHouse'      => [
                'id' => 1,
            ],
            'yearLevel'        => [
                'id' => 1,
            ],
            'impersonableUsers' => [
                [
                    'id' => 22,
                ],
            ],
            'externalId'       => $i->generateText(8, false),
            'username'         => $i->generateText(8, true),
            'superuser'        => false,
            'enabled'          => true,
            'title'            => null,
            'firstName'        => $i->generateText(8, true),
            'lastName'         => $i->generateText(10, true),
            'preferredName'    => $i->generateText(7, true),
            'isEmailDerived'   => false,
            'altEmail'         => null,
            'positionTitle'    => $i->generateText(20, false),
            'flags'            => [
                'showContacts'           => true,
                'enableTimetable'        => true,
                'showSideNav'            => true,
                'showNotificationsPanel' => true,
                'showGradeGraph'         => true,
                'enableEportfolio'       => true,
                'useInternalMail'        => false,
                'calendarView'           => 'agendaWeek',
            ],
        ];
    }
}
