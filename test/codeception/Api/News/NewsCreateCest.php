<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\News;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;

/**
 * news@create.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api NewsCreateCest
 */
class NewsCreateCest
{
    private Folder $folder;

    public function _before(ApiTester $i): void
    {
        $this->folder = $i->getFolder();
    }


    /**
     * Create a news article
     * @param ApiTester $i
     * @throws ServiceNotFound
     */
    public function testPost(ApiTester $i): void
    {
        $users     = $i->getUsers();
        $superUser = $i->createTestUser('super-' . time(), Role::STAFF, true);

        $articleIds = [];

        foreach ($users as $user) {
            $i->switchUser($user);
            foreach ($this->getCreateTestData($i) as $postData) {
                $newsData = $postData['data'];
                $i->comment("Testing as user {$user->getUsername()} [{$user->getId()}]");
                $i->sendPost('/news/create', $newsData);
                $i->seeResponseCodeIs(200);
                $articleId = $i->grabDataFromResponseByJsonPath('$.articleId')[0];
                $i->sendGet('/news/' . $articleId);
                $i->sendPost('/news/moderation/' . $articleId . '/accept', []);
                if ($user->isSuperuser() || $user->getRole()->isStaff()) {
                    $i->seeResponseCodeIs(200);
                } else {
                    $i->seeResponseCodeIs(401);
                }
                $i->sendGet('/news/' . $articleId);
                $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/news/commonFields.yaml';
                if (file_exists($schemaFile)) {
                    $schema   = Yaml::parseFile($schemaFile);
                    $responseSchema = Yaml::parseFile('/tmp/schoolbox-api-docs/openapi/paths/news@{id}.yaml');

                    $props2= $responseSchema['get']['responses']['200']['content']['application/json']['schema']['properties']['article']['allOf'][1];
                    $schema = array_merge_recursive($schema, $props2);
                    // TODO: codecept doesn't seem to be detecting type of _links properly
                    unset($schema['properties']['featureImage']['properties']['_links']['type']);
                    $response = $i->grabDataFromResponseByJsonPath('$.article');
                    $i->checkSchema($response[0], $schema, '$.article');
                }


                $articleIds[] = $articleId;
            }
        }

        $i->switchUser($superUser);
        foreach ($articleIds as $articleId) {
            $threadData  = [
                'open'    => true,
                'created' => \Alaress_Date_Format::now()->addDays(-10)->getLocal(),
            ];
            $commentData = [
                'thread'  => [],
                'content' => $i->generateText(100),
                'created' => \Alaress_Date_Format::now()->addDays(-10)->getLocal(),
            ];

            $i->sendPostAsJson("/discussion/comms/$articleId/threads", $threadData);
            $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/discussionThread-read.yaml';
            if (file_exists($schemaFile)) {
                $schema   = Yaml::parseFile($schemaFile);
                $response = $i->grabDataFromResponseByJsonPath('$');
                $i->checkSchema($response[0], $schema, '$');
            }

            $threadId = $i->grabDataFromResponseByJsonPath('$.id')[0];
            $i->sendPost("/discussion/comms/$articleId/threads/$threadId/comments", $commentData);
            $i->seeResponseCodeIs(200);
            $i->sendGet("/discussion/comms/$articleId/threads/$threadId/comments", []);
            $schemaFile = '/tmp/schoolbox-api-docs/openapi/components/schemas/discussionComment-read.yaml';
            if (file_exists($schemaFile)) {
                $schema   = Yaml::parseFile($schemaFile);
                $response = $i->grabDataFromResponseByJsonPath('$.data[0]');
//                $i->checkSchema($response[0], $schema);
            }
        }
    }

    /**
     * @param ApiTester $i
     * @return Generator
     */
    private function getCreateTestData(ApiTester $i): Generator
    {
        yield [
            'data' => [
                'title'     => 'test',
                'approveAt' => \Alaress_Date_Format::now()->getLocal(),
                'publishAt' => \Alaress_Date_Format::now()->getLocal(),
                'expiresAt' => \Alaress_Date_Format::now()->addDays(20)->getLocal(),
                'audience'  => sprintf("%s:%s:%s", 'folder', base64_encode(json_encode("{$this->folder->getId()}")), 'self'),
            ],
        ];
        yield [
            'data' => [
                'title'         => 'test with fields',
                'approveAt'     => \Alaress_Date_Format::now()->getLocal(),
                'publishAt'     => \Alaress_Date_Format::now()->getLocal(),
                'expiresAt'     => \Alaress_Date_Format::now()->addDays(20)->getLocal(),
                'audience'      =>
                    sprintf("%s:%s:%s", 'folder', base64_encode(json_encode("{$this->folder->getId()}")), 'self')
                    . ',' .
                    sprintf("%s:%s:%s", 'folder', base64_encode(json_encode("{$this->folder->getId()}")), 'parents_of')
                ,
                'body'          => $i->generateText(100),
                'blurb'         => $i->generateText(50),
                'topics'        => [1],
                'sticky'        => 1,
                'attachment'    => ['store:723b6e31c2979b33deea852cdbbad9c4b0e00b96:piglet2.jpg'],
                'attachedFiles' => ['store:69903e5d89e1574d92de8afd1634f472e3096b3c:scrabble2.jpg'],
            ],
        ];
    }
}
