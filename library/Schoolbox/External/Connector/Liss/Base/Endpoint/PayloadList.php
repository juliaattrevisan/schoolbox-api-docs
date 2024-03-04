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
            ->like('filename', PayloadCache::INPUT_PAYLOAD_PREFIX . '%');

        $lissPayloads = $this->storageMapper->findByPredicates($pb)->sortBy('filename', 'desc');

        $scheduled = [
            PayloadCache::PAYLOAD_DAILY_DATA => [
                'title' => 'Daily Data',
                'file' => null
            ],
            PayloadCache::PAYLOAD_BELLTIMES => [
                'title' => 'Bell Times',
                'file' => null
            ],
            PayloadCache::PAYLOAD_CLASSES => [
                'title' => 'Classes',
                'file' => null
            ],
            PayloadCache::PAYLOAD_CLASS_MEMBERSHIPS => [
                'title' => 'Class Memberships',
                'file' => null
            ],
            PayloadCache::PAYLOAD_TIMETABLE => [
                'title' => 'Timetable',
                'file' => null
            ]
        ];

        foreach ($lissPayloads as $lissPayload) {
            foreach (PayloadCache::PAYLOAD_ALL as $payloadName) {
                if (null === $scheduled[$payloadName]['file'] && strpos($lissPayload->getFilename(), $payloadName) > -1) {
                    $scheduled[$payloadName]['file'] = $lissPayload;
                }
            }
        }

        return [
            'scheduled' => $scheduled,
            'all' => $lissPayloads
        ];
    }
}
