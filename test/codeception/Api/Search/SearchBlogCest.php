<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Search;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Schoolbox\Blog\Homepage\Post\Model as Blog;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run Api Search/SearchBlogCest
 */
class SearchBlogCest
{
    protected const SEARCH_TYPE = 'blog';
    private User $admin;

    /**
     * @param ApiTester $i
     * @return void
     * @throws ServiceNotFound
     */
    public function run(ApiTester $i)
    {
        $users       = $i->getUsers();
        $this->admin = $users['super'];
        foreach ($users as $user) {
            $this->testGet($i, $user);
        }
    }

    /**
     * Test /api/search for calendar events
     * @param ApiTester $i
     * @param User      $user
     * @throws ServiceNotFound
     */
    private function testGet(ApiTester $i, User $user): void
    {
        // prepare events
        $blogs = $this->prepareBlogs($i, $user);

        // Actual testing
        $i->switchUser($user);

        foreach ($blogs as $blogData) {
            /** @var Blog $blog */
            $blog          = $blogData['blog'];
            $expectedCount = $blogData['count'];
            foreach ($this->filterData($blog) as $filterData) {
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

                if ($dataCount === 0) {
                    continue;
                }

                $count = $i->grabDataFromResponseByJsonPath('$.metadata.count');
                $i->comment("$count[0] found.");

                $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/search/resultTypes/common.yaml';
                if (file_exists($schemaFile)) {
                    $schema = Yaml::parseFile($schemaFile);
                    $found  = false;
                    for ($n = 0; $n < $dataCount; $n++) {
                        $response  = $i->grabDataFromResponseByJsonPath("$.data[0]")[$n];
                        if (!isset($response['_subResults'])) {
                            $i->comment('_subResults not set');
                            continue;
                        }
                        $subResult = $response['_subResults']['data'][0];
                        if ($subResult['type'] !== self::SEARCH_TYPE) {
                            continue;
                        }

                        $i->checkSchema($subResult, $schema);
                        $found = true;
                        break;
                    }
                    $i->comment($filterData['keyword']);
                    $i->comment($blog->getHeading());
//                    $i->assertSame($user->isSuperuser() ? 1 : $expectedCount, $found ? 1 : 0);
                }
            }
        }
    }

    /**
     * @param Blog $blog
     * @return Generator
     */
    private function filterData(Blog $blog): Generator
    {
        $headingParts = explode(' ', $blog->getHeading());
        $bodyParts    = explode(' ', $blog->getBody());

        $heading      = $headingParts[0];
        $body         = $bodyParts[0];
        yield [
            'keyword' => $heading,
            'valid'   => true,
        ];
        yield [
            'keyword' => $heading,
            'filter'  => [
                'types' => [self::SEARCH_TYPE],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $body,
            'filter'  => [
                'types' => [self::SEARCH_TYPE],
                'date'  => [
                    'from' => $blog->getCreatedAt()->addDays(-1)->getUsingFormat(\Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $heading,
            'filter'  => [
                'types' => [self::SEARCH_TYPE],
                'date'  => [
                    'to' => $blog->getCreatedAt()->addDays(+1)->getUsingFormat(\Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $body,
            'filter'  => [
                'types'   => [self::SEARCH_TYPE],
                'creator' => [
                    'id' => $blog->getOwner()->getId(),
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
            'keyword' => $heading,
            'filter'  => [
                'types' => self::SEARCH_TYPE,
            ],
            'valid'   => false, // not array
        ];
    }

    /**
     * @param ApiTester $i
     * @param User      $user
     * @return Generator
     * @throws ServiceNotFound
     */
    private function prepareBlogs(ApiTester $i, User $user): Generator
    {
        $folder = $i->getFolder();
        $i->switchUser($this->admin);

        yield [
            'blog'  => $i->createBlog(
                $user,
                $folder
            ),
            'count' => 1,
        ];

        yield [
            'blog'  => $i->createBlog(
                $this->admin,
                $folder
            ),
            'count' => 1,
        ];
    }
}
