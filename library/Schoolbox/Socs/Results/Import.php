<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Results;

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
    private const RESULTS_PATH = 'results.ashx';

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
        $now = \Alaress_Date_Format::now();
        $startDate = (clone $now)->addDays(-30)->getUsingFormat('j M Y');
        $endDate = (clone $now)->addDays(100)->getUsingFormat('j M Y');

        $this->log->info(sprintf('Downloading SOCS results from %s to %s', $startDate, $endDate));

        $query = [
            'startdate' => $startDate,
            'enddate'   => $endDate,
        ];

        $response = $this->httpClient->get(self::RESULTS_PATH, $query, $this->log);

        if (null === $response) {
            $this->log->info('SOCS results no response; skipping');
            return;
        }

        if ($response->getStatusCode() !== 200) {
            $this->log->warning(sprintf(
                'Bad SOCS results response: %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            return;
        }

        $body = (string)$response->getBody();
        $this->log->debug(sprintf(
            'SOCS results raw response: Content-Type %s; length %d',
            $response->getHeaderLine('Content-Type') ?: 'unknown',
            strlen($body)
        ));
        if ('' === $body) {
            $this->log->info('SOCS results feed returned an empty payload');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            $this->log->warning(sprintf(
                'Unable to parse SOCS results XML payload; aborting. First 200 chars: %s',
                substr($body, 0, 200)
            ));
            return;
        }

        $imported = 0;

        foreach ($xml->children() as $resultNode) {
            $this->lock->refresh();
            $eventId = trim((string)$resultNode->eventid);
            $resultText = trim((string)$resultNode->result);

            if ('' === $eventId || '' === $resultText) {
                $this->log->debug('Skipping SOCS result with missing mandatory fields');
                continue;
            }

            $externalId = 'socs:' . $eventId;

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
                $existingResults = $this->resultMapper->findByColumn('match_id', $matchup->getId());
                foreach ($existingResults as $existingResult) {
                    $this->resultMapper->delete($existingResult);
                }

                /** @var ResultModel $result */
                $result = $this->resultMapper->createModel();
                $result
                    ->setMatchup($matchup)
                    ->setTeam($homeTeam)
                    ->setDescription($resultText);

                $this->resultMapper->save($result);
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
