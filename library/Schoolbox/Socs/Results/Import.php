<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Results;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as MatchupModel;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Result\Model as ResultModel;
use Schoolbox\Fixtures\Team\Model as TeamModel;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * Import SOCS results from the standard results feed and attach them to existing fixtures.
 */
class Import implements IStrategy
{
    private const string RESULTS_PATH = 'results.ashx';

    private const string EXTERNAL_ID_PREFIX = 'socs:';

    /**
     * SOCS - Standard Fixture Feed
     * the api system defaults to, and this ensures we fetch data for the previous 30 days and next 100 days.
     */
    private const int FROM = -30;
    private const int TO = 100;

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private MatchupMapper $matchupMapper,
        private ResultMapper $resultMapper
    ) {
    }

    /**
     * Import SOCS results within a moving date window and attach them to fixtures.
     */
    public function import(): void
    {
        $startDate = \Alaress_Date_Format::now()->addDays(self::FROM)->getUsingFormat('j M Y');
        $endDate = \Alaress_Date_Format::now()->addDays(self::TO)->getUsingFormat('j M Y');

        $this->log->info(sprintf('Downloading SOCS results from %s to %s', $startDate, $endDate));

        $query = [
            'startdate' => $startDate,
            'enddate'   => $endDate,
        ];

        try {
            $response = $this->httpClient->get(self::RESULTS_PATH, $query);
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

        $imported = 0;

        foreach ($response->children() as $resultNode) {
            $this->lock->refresh();
            $eventId = trim((string)$resultNode->eventid);
            $resultText = trim((string)$resultNode->result);

            if ('' === $eventId) {
                $this->log->info('Skipping SOCS result with missing mandatory fields');
                continue;
            }

            $externalId = self::EXTERNAL_ID_PREFIX . $eventId;

            try {
                /** @var MatchupModel $matchup */
                $matchup = $this->matchupMapper->findOneByColumn('external_id', $externalId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log->debug(sprintf('Skipping SOCS result %s; no matching SOCS fixture found', $externalId));
                continue;
            }

            $homeTeam = $matchup->getHomeTeam();
            if (!$homeTeam instanceof TeamModel || !$homeTeam->getId()) {
                $this->log->debug(sprintf('Skipping SOCS result %s; matchup has no home team', $externalId));
                continue;
            }

            if ($this->perform) {
                /* If the resultDescription is null, it means this matchup
                     * shouldn't have a result; delete all results if they existed
                     */
                if ('' === $resultText) {
                    foreach ($matchup->getResults() as $currentResult) {
                        $this->resultMapper->delete($currentResult);
                    }
                } else {
                    /* There was something meaningful to say; save a new Result
                     * if one doesn't already exist for this matchup and team
                     */
                    try {
                        $result = $this->resultMapper->findOneByColumnList([
                            'match_id' => $matchup->getId(),
                            'team_id'  => $homeTeam->getId()
                        ]);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $result = $this->resultMapper->createModel()
                            ->setMatchup($matchup)
                            ->setTeam($homeTeam);
                    }

                    /* Update home team's description with whatever it is now
                     */
                    $result->setDescription($resultText);

                    $this->resultMapper->save($result);
                }
            }

            $imported++;
        }

        if ($this->perform) {
            $this->log->info(sprintf('SOCS results import complete; processed %d results', $imported));
        } else {
            $this->log->info(sprintf('SOCS results planning complete; would process %d results', $imported));
        }
    }
}
