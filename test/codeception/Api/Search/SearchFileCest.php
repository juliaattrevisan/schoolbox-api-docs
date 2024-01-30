<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Search;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Schoolbox\Resource\File\Model as File;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run Api Search/SearchFileCest
 */
class SearchFileCest
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
        $file = $i->getExistingFile();
        $i->switchUser($user);
        foreach ($this->filterData($file) as $filterData) {
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
     * @param File $file
     * @return Generator
     */
    private function filterData(File $file): Generator
    {
        $title = $file->getTitle();

        // Currently, search for a base type doesn't work:
        // must provide all subtypes instead.
        $subtypes = [
            'file.document',
            'file.image',
            'file.video',
            'file.audio',
            'file.other',
        ];

        yield [
            'keyword' => $title,
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => $subtypes,
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => $subtypes,
                'date'  => [
                    'from' => $file->getCreatedAt()->addDays(-1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => $subtypes,
                'date'  => [
                    'to' => (\Alaress_Date_Format::now())->addDays(1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types'   => $subtypes,
                'creator' => [
                    'id' => $file->getOwner()->getId(),
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
                'types' => 'file',
            ],
            'valid'   => false, // not array
        ];
    }
}
