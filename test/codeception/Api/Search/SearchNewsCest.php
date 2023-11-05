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
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run Api Search/SearchNewsCest
 */
class SearchNewsCest
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
     * Test /api/search for news articles
     * @param ApiTester $i
     * @param User      $user
     * @throws ServiceNotFound
     */
    private function testGet(ApiTester $i, User $user): void
    {
        $i->switchUser($user);
        foreach ($this->filterData() as $filterData) {
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

    /**
     * @return Generator
     */
    private function filterData(): Generator
    {
        $title = $this->article->getTitle();
        yield [
            'keyword' => $title,
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => ['news'],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => ['news'],
                'date'  => [
                    'from' => '2010-01-30',
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => ['news'],
                'date'  => [
                    'to' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types'   => ['news'],
                'creator' => [
                    'id' => $this->article->getAuthor()->getId(),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => '',
            'valid'   => false,
        ];
        yield [
            'valid' => false,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => 'news',
            ],
            'valid'   => false, // not array
        ];
    }
}
