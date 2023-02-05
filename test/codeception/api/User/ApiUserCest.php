<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace API;

use Schoolbox\Role;
use Schoolbox\User;

/**
 * user.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api ApiUserCest
 */
class ApiUserCest
{
    public function _before(\ApiTester $i)
    {
    }


    /**
     * Get a list of assessments
     * @param \ApiTester $i
     */
    public function testGet(\ApiTester $i)
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

    private function filterData(\ApiTester $i)
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
                'folder' => [
                    [
                        'code' => '9ENGA',
                    ],
                ],
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'roleType' => Role::GUEST,
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'subjectCode' => ['English'],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'weighted' => false,
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'weighted' => 'xxx',
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'workType' => [
                    'id' => 1,
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'workType' => [
                    'name' => 'Quiz',
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'dueDate' => [
                    'from' => '2010-01-30T21:00:00.000Z',
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'dueDate' => [
                    'to' => '2022-01-30T21:00:00.000Z',
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'dueDate' => '2010-01-30T21:00:00.000Z',
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'due Date' => [
                    'from' => '2010-01-30T21:00:00.000Z',
                ],
            ],
            'valid'  => false,
        ];
    }
}
