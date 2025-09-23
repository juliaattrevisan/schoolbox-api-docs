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
