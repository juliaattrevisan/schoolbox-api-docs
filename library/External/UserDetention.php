<?php

/**
 * Wrapper class for user detention external connectors.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Redis;
use External\CachingConnectorAbstract;
use Schoolbox\Config\Loader;

class External_UserDetention extends CachingConnectorAbstract implements External_UserDetentionInterface
{


    /**
     * Constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        // TODO inject dependencies
        parent::__construct(
            (new External_DatabaseFactory())->buildUserDetentionConnector(Loader::getInstance()),
            new External_Offline_UserDetention(),
            Redis::getInstance(),
            Config::get('external_cache_timeout')
        );
    }

    /**
     * @inheritDoc
     */
    public function getAwards(string $externalId, string $year, string $semester): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getDetentions(string $externalId, DateTime $fromDate = null, DateTime $toDate = null): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getAbsences(string $externalId, DateTime $fromDate = null, DateTime $toDate = null): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }
}
