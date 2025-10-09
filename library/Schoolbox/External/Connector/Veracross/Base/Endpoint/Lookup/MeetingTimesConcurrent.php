<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Classes;

/**
 * Grab all the Meeting Times everywhere
 */
class MeetingTimesConcurrent
{
    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $lookup = null;


    private const CLASS_CHUNK_SIZE = 100;


    /**
     * Constructor
     *
     * @param IApi $api
     * @param array $classIds
     * @param LoggerInterface $log
     */
    public function __construct(
        private IApi $api,
        private array $classIds,
        private LoggerInterface $log
    ) {
    }


    /**
     * Generate a compiled list of records, indexed by an identifier
     *
     * @param string $from
     * @param string $to
     * @return array
     * @throws \JsonException
     * @throws \Throwable
     */
    public function generate(string $from, string $to): array
    {
        $this->log->debug('Prefetching meeting times...');

        $start = microtime(true);

        /* If we generated this lookup previously, just recycle it
         */
        if (null === $this->lookup) {
            $this->lookup = [];

            $classChunks = array_chunk($this->classIds, self::CLASS_CHUNK_SIZE);

            foreach ($classChunks as $classChunk) {
                $endpoints = [];

                /* Compile a set of endpoints to hit concurrently
                 */
                foreach ($classChunk as $classId) {
                    $endpoints[] = sprintf(
                        'academics/classes/%s/meeting_times',
                        $classId
                    );
                }

                /* This returns a set of guzzle response instances in a generic array but it presumes one page is
                 * all you needed. Each page contains 1000 elements (1000 meeting times per class) which should be
                 * more than enough in practice. If a class ran five times a day for a whole month you'd be looking
                 * at obtaining 100 meeting times elements; you could supply 10 months of meeting times under this
                 * paradigm. At most we use 6 months, and even then, I've just halved it to 3 months.
                 */
                $concurrencyStart = microtime(true);
                $responses = $this->api->retrieveConcurrent('GET', $endpoints, Classes::SCOPE, [
                    'on_or_after_date' => $from,
                    'on_or_before_date' => $to,
                ]);
                $concurrencyEnd = microtime(true);

                $this->log->debug(sprintf(
                    'Concurrently fetching meeting times for %s classes took %s seconds to resolve all promises.',
                    \count($endpoints),
                    number_format($concurrencyEnd - $concurrencyStart, 2)
                ));

                foreach ($responses as $response) {
                    if ($response->getStatusCode() === 200) {
                        $content = $response->getBody()->getContents();

                        if ('' === $content) {
                            return [];
                        }

                        /* TODO Restore retry, armouring and error handling
                         */
                        $page = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

                        $classId = null;

                        foreach ($page['data'] as $record) {
                            $classId = $record['internal_class_id'];

                            $this->lookup[$classId][] = $record;
                        }

                        /* We know this $classId if there were $records to process.
                         * There won't be a $classId for an empty set, so no need to log anything about that.
                         */
                        if ($classId) {
                            $this->log->debug(sprintf(
                                'Processing %s meeting times for class %s.',
                                \count($page['data']),
                                $classId
                            ));
                        }
                    }
                }
            }
        }

        $end = microtime(true);

        $this->log->debug(sprintf(
            'Prefetching meeting times took %s seconds',
            number_format($end - $start, 2)
        ));

        $this->log->debug('');

        return $this->lookup;
    }
}
