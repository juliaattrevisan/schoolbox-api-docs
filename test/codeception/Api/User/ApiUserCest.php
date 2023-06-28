<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\User;

use Tests\Support\ApiTester;
use Schoolbox\Role;
use Schoolbox\User;

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
     */
    public function testGet(ApiTester $i)
    {
        $users = $i->getUsers();


        /** @var User $user */
        foreach ($users as $user) {
            $i->switchUser($user);

            foreach ($this->filterData($i) as $filterData) {
                if (isset($filterData['search'])) {
                    $data = [
                        'search' => $filterData['search'],
                        'limit' => 7
                    ];
                } else {
                    $data = [
                        'filter' => json_encode($filterData['filter']),
                        'limit' => 7
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

    private function filterData(ApiTester $i)
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
}
