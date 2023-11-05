<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Search;

use Aura\Di\Exception\ServiceNotFound;
use Generator;
use Schoolbox\Calendar\Event;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run Api Search/SearchEventCest
 */
class SearchEventCest
{
    protected const SEARCH_TYPE = 'event';
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
        $events = $this->prepareEvents($i, $user);

        // Actual testing
        $i->switchUser($user);

        foreach ($events as $eventData) {
            $event         = $eventData['event'];
            $expectedCount = $eventData['count'];
            foreach ($this->filterData($event) as $filterData) {
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
                        $response = $i->grabDataFromResponseByJsonPath("$.data[0]")[$n];
                        if (isset($filterData['filter']['type'])) {
                            $i->assertSame($filterData['filter']['type'], [self::SEARCH_TYPE]);
                        }
                        if ($response['type'] !== self::SEARCH_TYPE) {
                            continue;
                        }
                        $i->checkSchema($response, $schema);
                        $found = true;
                        break;
                    }
                    $i->comment($filterData['keyword']);
                    $i->comment($event->getTitle());
//                    $i->assertSame($user->isSuperuser() ? 1 : $expectedCount, $found ? 1 : 0);
                }
            }
        }
    }

    /**
     * @param Event $event
     * @return Generator
     */
    private function filterData(Event $event): Generator
    {
        $title = $event->getTitle();
        yield [
            'keyword' => $title,
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => [self::SEARCH_TYPE],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => [self::SEARCH_TYPE],
                'date'  => [
                    'from' => $event->getStartDateTime()->addDays(-1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types' => [self::SEARCH_TYPE],
                'date'  => [
                    'to' => $event->getStartDateTime()->addDays(+1)->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ],
            ],
            'valid'   => true,
        ];
        yield [
            'keyword' => $title,
            'filter'  => [
                'types'   => [self::SEARCH_TYPE],
                'creator' => [
                    'id' => $event->getAuthor()->getId(),
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
    private function prepareEvents(ApiTester $i, User $user): Generator
    {
        $folder = $i->getFolder();
        $i->switchUser($this->admin);

        // personal event
        yield [
            'event' => $i->createEvent(
                $user,
                Event::ACCESS_PERSONAL,
                Event\Attendance\Model::LEVEL_NO_ATTENDANCE,
                true,
                null
            ),
            'count' => 1,
        ];

        // another user's event
        yield [
            'event' => $i->createEvent(
                $this->admin,
                Event::ACCESS_PERSONAL,
                Event\Attendance\Model::LEVEL_NO_ATTENDANCE,
                true,
                null
            ),
            'count' => $user->isSuperuser() ? 1 : 0,
        ];
        // invited event
        $invitedEvent = $i->createEvent(
            $this->admin,
            Event::ACCESS_PERSONAL,
            Event\Attendance\Model::LEVEL_INVITE_ONLY,
            true,
            null
        );
        $i->sendPost("/calendar/event/attendance/{$invitedEvent->getId()}/create", ['guests' => $user->getId()]);
        yield [
            'event' => $invitedEvent,
            'count' => 1,
        ];

        // group event
//        yield [
//            'event' => $i->createEvent(
//                $this->admin,
//                Event::ACCESS_GROUP,
//                Event\Attendance\Model::LEVEL_INVITE_ONLY,
//                false,
//                $folder
//            ),
//            'count' => 1,
//        ];
    }
}
