<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Teams;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Socs\Fixtures\RecallSupport;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * Import SOCS teams from the detailed mso-sport feed.
 */
class Import implements IStrategy
{
    use RecallSupport;

    private const string MSO_SPORT_PATH = 'mso-sport.ashx';

    public function __construct(
        private LoggerInterface $log,
        bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private SeasonMapper $seasonMapper,
        private SportMapper $sportMapper,
        private DivisionMapper $divisionMapper,
        private TeamMapper $teamMapper
    ) {
        $this->perform = $perform;
    }

    /**
     * Import SOCS teams from the mso-sport feed and ensure they exist in the hierarchy.
     */
    public function import(): void
    {
        $season = $this->recallSeason($this->seasonMapper, $this->log);

        $this->log->info('Downloading SOCS teams from mso-sport feed');

        $query = [];

        try {
            $response = $this->httpClient->get(self::MSO_SPORT_PATH, $query, $this->log);
        } catch (GuzzleException $e) {
            $this->log->warning(sprintf('Failed to contact SOCS teams endpoint: %s', $e->getMessage()));
            return;
        }

        if ($response->getStatusCode() !== 200) {
            $this->log->warning(sprintf(
                'Bad SOCS teams response: %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            return;
        }

        $body = (string)$response->getBody();
        $this->log->debug(sprintf(
            'SOCS teams raw response: Content-Type %s; length %d',
            $response->getHeaderLine('Content-Type') ?: 'unknown',
            strlen($body)
        ));
        if ('' === $body) {
            $this->log->info('SOCS teams feed returned an empty payload');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            $this->log->warning(sprintf(
                'Unable to parse SOCS teams XML payload; aborting. First 200 chars: %s',
                substr($body, 0, 200)
            ));
            return;
        }

        /** @var \SimpleXMLElement[]|false $teams */
        $teams = $xml->xpath('//team');
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

            $sport = $this->recallSport($this->sportMapper, $this->log, $sportName);

            $divisionName = $teamName;
            $division = $this->recallDivision($this->divisionMapper, $this->log, $divisionName, $sport, $season);

            $this->recallTeam($this->teamMapper, $this->log, $teamName, $division);

            $imported++;
        }

        if ($this->perform) {
            $this->log->info(sprintf('SOCS teams import complete; processed %d teams', $imported));
        } else {
            $this->log->info(sprintf('SOCS teams planning complete; would process %d teams', $imported));
        }
    }
}
