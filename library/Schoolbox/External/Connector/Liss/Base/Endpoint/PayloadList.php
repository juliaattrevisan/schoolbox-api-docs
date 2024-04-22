<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
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

        $scheduled = [
            PayloadCache::PAYLOAD_DAILY_DATA => [
                'title' => 'Daily Data',
                'sources' => [
                    PayloadCache::DEFAULT_SOURCE_NAME => null
                ]
            ],
            PayloadCache::PAYLOAD_BELLTIMES => [
                'title' => 'Bell Times',
                'sources' => [
                    PayloadCache::DEFAULT_SOURCE_NAME => null
                ]
            ],
            PayloadCache::PAYLOAD_CLASSES => [
                'title' => 'Classes',
                'sources' => [
                    PayloadCache::DEFAULT_SOURCE_NAME => null
                ]
            ],
            PayloadCache::PAYLOAD_CLASS_MEMBERSHIPS => [
                'title' => 'Class Memberships',
                'sources' => [
                    PayloadCache::DEFAULT_SOURCE_NAME => null
                ]
            ],
            PayloadCache::PAYLOAD_TIMETABLE => [
                'title' => 'Timetable',
                'sources' => [
                    PayloadCache::DEFAULT_SOURCE_NAME => null
                ]
            ]
        ];

        foreach ($lissPayloads as $lissPayload) {
            $parts = explode('-', $lissPayload->getFilename());

            /* "default", normally
             *
             * ...unless the school specified a ?source parameter in their Receiver all,
             * in which case it'll be that. Redlands used it.
             */
            $source = $parts[2];

            foreach (PayloadCache::PAYLOAD_ALL as $payloadName) {
                $neverSeen = null === ($scheduled[$payloadName]['sources'][$source] ?? null);
                $matches = strpos($lissPayload->getFilename(), $payloadName) > -1;

                if ($neverSeen && $matches) {
                    $scheduled[$payloadName]['sources'][$source] = $lissPayload;
                }
            }
        }

        return [
            'scheduled' => $scheduled,
            'all' => $lissPayloads
        ];
    }
}
