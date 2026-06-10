<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Assessment;

use Aura\Di\Exception\ServiceNotFound;
use Codeception\Scenario;
use Generator;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
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

    /**
     * @param ApiTester $i
     * @return void
     * @throws ServiceNotFound
     */
    public function run(ApiTester $i, Scenario $scenario): void
    {
        $scenario->skip('Skipped pending rubric restructure fix [SA-22466].');

        $this->dueWork = $i->getExistingAssessment(null);
        $this->folder = $this->dueWork->getBaseContext()->getFolder();

        $users = $i->getUsers();
        foreach ($users as $user) {
            $this->testGet($i, $user);
        }
    }

    /**
     * Get a list of assessments
     * @param ApiTester $i
     * @param User      $user
     * @throws ServiceNotFound
     */
    private function testGet(ApiTester $i, User $user): void
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
                $i->comment("$dataCount retrieved.");
                $count = $i->grabDataFromResponseByJsonPath('$.metadata.count');
                $i->comment("$count[0] found.");
                $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/assessment-item.yaml';
                if ($dataCount > 0 && file_exists($schemaFile)) {
                    $schema   = Yaml::parseFile($schemaFile);
                    $response = $i->grabDataFromResponseByJsonPath("$.data[0]");
                    $i->checkSchema($response[0], $schema);
                }
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }

    /**
     * @return Generator
     */
    private function filterData(): Generator
    {
        yield [
            'filter' => [
                'assessmentId' => $this->dueWork->getId(),
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'assessmentId' => '1e3'
            ],
            'valid'  => false,
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
                'folder' => [
                    [
                        // This use case is not useful and probably ought to be rejected.
                        // However, it was allowed initially, so users might depend on it.
                        'id' => $this->folder->getId(),
                        'code' => $this->folder->getCodeString(),
                    ],
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'folder' => [
                    'code' => $this->folder->getCodeString(),
                ],
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'folder' => 83
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'folder' => [83, 16]
            ],
            'valid'  => false,
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
                // This use case is not useful and probably ought to be rejected.
                // However, it was allowed initially, so users might depend on it.
                'workType' => [
                    'id' => 1,
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
        yield [
            'filter' => [
                'unknownParameter' => 1
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'yearLevel' => 1
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'yearLevel' => [1, 2]
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'project' => 1
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'project' => [1, 2]
            ],
            'valid'  => false,
        ];
        yield [
            'filter' => [
                'yearLevel' => [
                    'id' => 3,
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                'yearLevel' => [
                    'name' => "9",
                ],
            ],
            'valid'  => true,
        ];
        yield [
            'filter' => [
                // This use case is not useful and probably ought to be rejected.
                // However, it was allowed initially, so users might depend on it.
                'yearLevel' => [
                    'id' => 3,
                    'name' => "9",
                ],
            ],
            'valid'  => true,
        ];
    }
}
