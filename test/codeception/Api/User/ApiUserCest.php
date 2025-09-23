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
     */
    public function testGet(ApiTester $i): void
    {
        $users = $i->getUsers();

        foreach ($users as $user) {
            $i->switchUser($user);

            foreach ($this->filterData($i) as $filterData) {
                if (isset($filterData['search'])) {
                    $data = [
                        'search' => $filterData['search'],
                        'limit'  => 7,
                    ];
                } else {
                    $data = [
                        'filter' => json_encode($filterData['filter']),
                        'limit'  => 7,
                    ];
                }
                $i->sendGet('/api/user', $data);
                if ($user->isSuperuser()) {
                    if ($filterData['valid']) {
                        $i->seeResponseCodeIs(200);
                    } else {
                        $i->seeResponseCodeIs(400);
                        $i->comment(implode('', $i->grabDataFromResponseByJsonPath('$.title')));
                        continue;
                    }
                    $dataCount = count($i->grabDataFromResponseByJsonPath('$.data')[0]);
                    $i->comment("{$dataCount} retrieved.");
                    $count = $i->grabDataFromResponseByJsonPath('$.metadata.count');
                    $i->comment("{$count[0]} found.");
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
    private function filterData(ApiTester $i): Generator
    {
        yield [
            'search' => "reed",
            'valid'  => true,
        ];
        yield [
            'filter' => [
                // demo data set
                'externalId' => "201",
            ],
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
            // Null filter.
            'filter' => null,
            'valid'  => true,
        ];
        yield [
            // Blank string filter.
            'filter' => '',
            'valid'  => false,
        ];
        yield [
            'filter' => [
                // Year/role/campus in 'id' format.
                'year' => [
                    'id' => 4 // Year '9' from db table `lu_year_level`.
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
                    ['id' => '1'], // Year '3' from db table `lu_year_level`.
                    ['name' => '9'], // From db table `lu_year_level`.
                ],
                'role' => [
                    ['id' => 5], // Role 'Junior Student' from db table `groups`.
                    ['name' => 'Middle Student'], // From db table `groups`.
                ],
                'campus' => [
                    ['id' => 2], // Campus 'Primary' from db table `luCampus`.
                    ['name' => 'Secondary'], // From db table `luCampus`.
                ],
            ],
            'valid' => true,
        ];
        yield [
            'filter' => [
                // Year/role/campus redundantly specified.
                'year' => [
                    'id' => 4, // Year '9' from db table `lu_year_level`.
                    'name' => '9', // From db table `lu_year_level`.
                ],
                'role' => [
                    'id' => 6, // Role 'Middle Student' from db table `groups`.
                    'name' => 'Middle Student', // From db table `groups`.
                ],
                'campus' => [
                    ['id' => 1], // Campus 'Secondary' from db table `luCampus`.
                    ['name' => 'Secondary'], // From db table `luCampus`.
                ],
            ],
            'valid' => true,
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
                'externalId' => null,
                'username' => null,
                'email' => null,
                'roleType' => null,
            ],
            'valid' => false,
        ];
        yield [
            'filter' => [
                // Non-existent roleType.
                'roleType' => 'Non-existent role type',
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
