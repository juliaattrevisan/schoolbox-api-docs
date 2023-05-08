<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Test\Api;

use ApiTester;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * assessment.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api ApiAssessmentCest
 */
class ApiAssessmentCest
{
    private Folder $folder;

    private IAssessable $dueWork;

    public function _before(ApiTester $i)
    {
        // rely on an existing assessment for now
        $this->dueWork = $i->getExistingAssessment();
        $this->folder = $this->dueWork->getBaseContext()->getFolder();
    }

    /**
     * @param ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function run(ApiTester $i)
    {
        $users = $i->getUsers();
        /** @var User $user */
        foreach ($users as $user) {
            $this->testGet($i, $user);
        }
    }

    /**
     * Get a list of assessments
     * @param ApiTester $i
     * @param User      $user
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function testGet(ApiTester $i, User $user)
    {
        $i->switchUser($user);
        foreach ($this->filterData() as $filterData) {
            $data = [
                'filter' => json_encode($filterData['filter']),
                'limit'  => 7,
            ];
            $i->sendGet('/api/assessment', $data);
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

    private function filterData()
    {
        yield [
            'filter' => [
                'assessmentId' => $this->dueWork->getId(),
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'title' => 'Character',
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'assessmentType' => 'xxx',
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'assessmentType' => 'dueWork',
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'folder' => [
                    'id' => $this->folder->getId(),
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'folder' => [
                    [
                        'code' => $this->folder->getCodeString(),
                    ],
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                // course subject code
                'subjectCode' => 'English',
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
