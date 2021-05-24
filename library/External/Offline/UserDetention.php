<?php

/**
 * Offline stub for External_UserDetentionInterface
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use External\IOfflineConnector;

class External_Offline_UserDetention implements External_UserDetentionInterface, IOfflineConnector
{
    /**
     * @inheritDoc
     */
    public function getAwards(string $externalId, string $year, string $semester): array
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function getDetentions(string $externalId, DateTime $fromDate = null, DateTime $toDate = null): array
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function getAbsences(string $externalId, DateTime $fromDate = null, DateTime $toDate = null): array
    {
        return array();
    }
}
