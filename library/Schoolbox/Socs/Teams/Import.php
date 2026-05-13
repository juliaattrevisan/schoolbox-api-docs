<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Teams;

use Psr\Log\LoggerInterface;
use Schoolbox\Socs\Fixtures\Registry;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * Import SOCS teams from the detailed mso-sport feed.
 */
class Import implements IStrategy
{
    private const string MSO_SPORT_PATH = 'mso-sport.ashx';

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private Registry $registry
    ) {
    }

    /**
     * Import SOCS teams from the mso-sport feed and ensure they exist in the hierarchy.
     */
    public function import(): void
    {
        $season = $this->registry->recallSeason();

        $this->log->info('Downloading SOCS teams from mso-sport feed');

        $query = [];

        try {
            $response = $this->httpClient->get(self::MSO_SPORT_PATH, $query);
        } catch (\RuntimeException $e) {
            $this->log->warning(sprintf('Failed to contact SOCS fixtures endpoint: %s', $e->getMessage()));
            if ($e->getPrevious()) {
                $this->log->debug(sprintf('Previous: %s', $e->getPrevious()->getMessage()));
                $this->log->debug($e->getPrevious()->getTraceAsString());
            }
            return;
        }

        if (false === $response) {
            $this->log->info('SOCS fixtures feed returned an empty payload');
            return;
        }

        /** @var \SimpleXMLElement[]|false $teams */
        $teams = $response->xpath('//team');
        if (false === $teams || [] === $teams) {
            $this->log->info('SOCS teams feed contained no team records');
            return;
        }

        $imported = 0;

        foreach ($teams as $teamNode) {
            $this->lock->refresh();
            $sportName = trim((string)$teamNode->sport);
            $teamName = trim((string)$teamNode->teamname);

            if ('' === $sportName || '' === $teamName) {
                $this->log->debug('Skipping SOCS team with missing mandatory fields');
                continue;
            }

            $sport = $this->registry->recallSport($sportName);

            $divisionName = $teamName;
            $division = $this->registry->recallDivision($divisionName, $sport, $season);

            $this->registry->recallTeam($teamName, $division);

            $imported++;
        }

        if ($this->perform) {
            $this->log->info(sprintf('SOCS teams import complete; processed %d teams', $imported));
        } else {
            $this->log->info(sprintf('SOCS teams planning complete; would process %d teams', $imported));
        }
    }
}
