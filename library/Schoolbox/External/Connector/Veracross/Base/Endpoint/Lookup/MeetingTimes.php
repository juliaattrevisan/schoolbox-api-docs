<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Classes;

/**
 * Grab all the Meeting Times everywhere
 */
class MeetingTimes
{
    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $lookup = null;


    /**
     * Constructor
     *
     * @param IApi $api
     * @param array $classIds
     */
    public function __construct(private IApi $api, private array $classIds)
    {
    }


    /**
     * Generate a compiled list of records, indexed by an identifier
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function generate(string $from, string $to): array
    {
        /* If we generated this lookup previously, just recycle it
         */
        if (null === $this->lookup) {
            $this->lookup = [];

            foreach ($this->classIds as $classId) {
                $endpoint = sprintf(
                    'academics/classes/%s/meeting_times',
                    $classId
                );

                /* Sadly you have to specify an internal_class_id individually
                 * There isn't a meetingtimes:list mechanism afaict
                 */
                $generator = $this->api->retrieve('GET', $endpoint, Classes::SCOPE, [
                    'on_or_after_date' => $from,
                    'on_or_before_date' => $to,
                ]);

                foreach ($generator as $page) {
                    /* Avengers Assemble!
                     */
                    foreach ($page['data'] as $record) {
                        $this->lookup[$classId][] = $record;
                    }
                }
            }
        }

        return $this->lookup;
    }
}
