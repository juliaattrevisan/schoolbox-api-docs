<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Search;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Schoolbox\Comms\News\Model as News;
use Schoolbox\Role;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run Api Search/SearchNewsCest
 */
class SearchUserCest
{
    private News $article;

    public function _before(ApiTester $i)
    {
        // rely on an existing assessment for now
        $this->article = $i->getExistingNewsArticle(null);
    }

    /**
     * @param ApiTester $i
     * @return void
     * @throws ServiceNotFound
     */
    public function run(ApiTester $i)
    {
        $users = $i->getUsers();
        foreach ($users as $user) {
            $this->testGet($i, $user);
        }
    }

    /**
     * Test /api/search for users
     * @param ApiTester $i
     * @param User      $user
     * @throws ServiceNotFound
     */
    private function testGet(ApiTester $i, User $user): void
    {
        $targetStudent = $i->createTestUser('student' . $i->generateText(10, true), Role::STUDENT, false);
        $targetStaff   = $i->createTestUser('staff' . $i->generateText(10, true), Role::STAFF, false);
        $targetUsers   = [$targetStudent, $targetStaff];
        $i->switchUser($user);
        foreach ($targetUsers as $targetUser) {
            foreach ($this->filterData($targetUser) as $filterData) {
                if (isset($filterData['filter'])) {
                    $filterData['filter'] = json_encode($filterData['filter']);
                }
                $i->sendGet('/api/search', $filterData);
                if ($filterData['valid']) {
                    $i->seeResponseCodeIs(200);
                } else {
                    $i->seeResponseCodeIs(400);
                    $i->comment(implode('', $i->grabDataFromResponseByJsonPath('$.title')));
                    continue;
                }
                $dataCount = count($i->grabDataFromResponseByJsonPath('$.data')[0]);
                $i->comment("$dataCount retrieved.");

                // TODO: check the other users
                if ($user->isSuperuser()) {
                    $i->assertTrue($dataCount > 0);
                }
                if ($dataCount === 0) {
                    continue;
                }

                $count = $i->grabDataFromResponseByJsonPath('$.metadata.count');
                $i->comment("$count[0] found.");

                $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/search/resultTypes/common.yaml';
                if (file_exists($schemaFile)) {
                    $schema   = Yaml::parseFile($schemaFile);
                    $response = $i->grabDataFromResponseByJsonPath("$.data[0]")[0];
                    $i->checkSchema($response, $schema);
                }
            }
        }
    }

    /**
     * @param User $user
     * @return Generator
     */
    private function filterData(User $user): Generator
    {
        yield [
            'keyword' => $user->getUsername(),
            'valid'   => true,
        ];
        yield [
            'keyword' => $user->getFirstname(),
            'filter'  => [
                'types' => ['user'],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $user->getLastname(),
            'filter'  => [
                'types' => ['user'],
                'date'  => [
                    'from' => '2010-01-30',
                ],
            ],
            'valid'   => ($user->getLastname() !== null),
        ];
        yield [
            'keyword' => $user->getFirstname(),
            'filter'  => [
                'types' => ['user'],
                'date'  => [
                    'to' => (\Alaress_Date_Format::now())->addDays(1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $user->getPreferredName(),
            'filter'  => [
                'types'   => ['user'],
                'creator' => [
                    'id' => $this->article->getAuthor()->getId(),
                ],
            ],
            'valid'   => ($user->getPreferredName() !== null),
        ];
        yield [
            'keyword' => '',
            'valid'   => false,
        ];
        yield [
            'valid' => false,
        ];
        yield [
            'keyword' => $user->getUsername(),
            'filter'  => [
                'types' => 'user',
            ],
            'valid'   => false, // not array
        ];
    }
}
