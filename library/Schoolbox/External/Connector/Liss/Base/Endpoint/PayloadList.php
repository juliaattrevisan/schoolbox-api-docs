<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Codec\PayloadFilename;
use Schoolbox\Resource\Storage\Mapper;

/**
 * Get a list of suds liss log files created by enabling
 * External Liss Logging
 */
class PayloadList
{
    /**
     * Constructor.
     */
    public function __construct(private Mapper $storageMapper)
    {
    }


    /**
     * Get a list of storage files that match a particular prefix
     *
     * @return array
     * @throws InvalidColumnException
     */
    public function generate(): array
    {
        $pb = $this->storageMapper->getPredicateBuilder()
            ->like('filename', Factory::SOURCE_NAME . '%');

        $lissPayloads = $this->storageMapper->findByPredicates($pb)->sortBy('filename', 'desc');

        /* Sources is a set of (source name, storage model) tuples
         */
        $scheduled = [
            PayloadCache::PAYLOAD_DAILY_DATA => [
                'title' => 'Daily Data',
                'sources' => []
            ],
            PayloadCache::PAYLOAD_BELLTIMES => [
                'title' => 'Bell Times',
                'sources' => []
            ],
            PayloadCache::PAYLOAD_CLASSES => [
                'title' => 'Classes',
                'sources' => []
            ],
            PayloadCache::PAYLOAD_CLASS_MEMBERSHIPS => [
                'title' => 'Class Memberships',
                'sources' => []
            ],
            PayloadCache::PAYLOAD_TIMETABLE => [
                'title' => 'Timetable',
                'sources' => []
            ]
        ];

        /* Derive created_at timestamp from the filename itself because
         * of an idiosyncracy in our storage table reusing rows whenever
         * there is a hash collision (leaves created_at untouched) since
         * it misrepresents what actually happened.
         */
        $decoder = new PayloadFilename();

        foreach ($lissPayloads as $lissPayload) {
            /* Extract info that was encoded into the filename itself
             */
            $parts = $decoder->decode($lissPayload->getFilename());

            /* "default", normally
             *
             * ...unless the school specified a ?source parameter in their Receiver all,
             * in which case it'll be that. Redlands used it.
             */
            $sourceName = $parts['sourceName'];

            /* Forcibly override storage's datetime
             */
            $lissPayload->setCreatedAt(new \Alaress_Date_Format($parts['date'], 'Ymd_His'));

            foreach (PayloadCache::PAYLOAD_ALL as $payloadName) {
                $neverSeen = null === ($scheduled[$payloadName]['sources'][$sourceName] ?? null);
                $matches = strpos($lissPayload->getFilename(), $payloadName) > -1;

                if ($neverSeen && $matches) {
                    $scheduled[$payloadName]['sources'][$sourceName] = $lissPayload;
                }
            }
        }

        return [
            'scheduled' => $scheduled,
            'all' => $lissPayloads
        ];
    }
}
