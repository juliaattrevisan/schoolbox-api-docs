<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\GenericCollection;
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
     * @return GenericCollection
     * @throws InvalidColumnException
     */
    public function generate(): GenericCollection
    {
        $pb = $this->storageMapper->getPredicateBuilder()
            ->like('filename', PayloadCache::INPUT_PAYLOAD_PREFIX . '%');

        return $this->storageMapper->findByPredicates($pb)->sortBy('createdAt', 'desc');
    }
}
