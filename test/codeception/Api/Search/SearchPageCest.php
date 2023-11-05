<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Search;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Schoolbox\Resource\Folder\Model as Folder;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run Api Search/SearchPageCest
 */
class SearchPageCest
{
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
        $folder = $i->getFolder();
        $i->switchUser($user);
        foreach ($this->filterData($folder) as $filterData) {
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
            if ($user->isSuperuser() && ($filterData['count'] ?? 0) > 0) {
                $i->assertTrue($dataCount > 0);
            }
            if ($dataCount === 0) {
                continue;
            }

//            $count = $i->grabDataFromResponseByJsonPath('$.metadata.count');
//            $i->comment("$count[0] found.");

            $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/search/resultTypes/common.yaml';
            if (file_exists($schemaFile)) {
                $schema           = Yaml::parseFile($schemaFile);
                $additionalSchema = Yaml::parseFile('/tmp/schoolbox-api-docs/openapi/components/schemas/search/resultTypes/page.yaml');
                $schema           = array_replace_recursive($schema, $additionalSchema['allOf'][1]);
                $response         = $i->grabDataFromResponseByJsonPath("$.data[0]")[0];
                $i->checkSchema($response, $schema);
            }
        }
    }

    /**
     * @param Folder $folder
     * @return Generator
     */
    private function filterData(Folder $folder): Generator
    {
        $title = $folder->getCodeString();
        yield [
            'keyword' => $title,
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => ['page'],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => ['page'],
                'date'  => [
                    'from' => (\Alaress_Date_Format::now())->addDays(-1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
            'count'   => 0,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => ['page'],
                'date'  => [
                    'to' => (\Alaress_Date_Format::now())->addDays(1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
            'count'   => 0,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types'   => ['page'],
                'creator' => [
                    'id' => 1,
                ],
            ],
            'valid'   => true,
            'count'   => 0,
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
                'types' => 'page',
            ],
            'valid'   => false, // not array
        ];
    }
}
