<?php

declare(strict_types=1);

namespace Schoolbox\Socs;

use DomainException;
use GuzzleHttp\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\GenericCollection;
use SensitiveParameter;
use Symfony\Component\Lock\LockInterface;

/**
 * Build the SOCS import pipeline as a collection of strategies.
 */
class Factory
{
    private ?HttpClient $httpClient = null;

    /**
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param LockInterface   $lock
     * @param string          $host
     * @param string          $schoolId
     * @param string          $token
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private string $host,
        private string $schoolId,
        #[SensitiveParameter] private string $token
    ) {
    }

    /**
     * Produce the ordered list of SOCS strategies to execute.
     *
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function produce(bool $fixtures, bool $results, bool $teams): GenericCollection
    {
        if ('' === $this->host) {
            throw new DomainException('Missing SOCS endpoint host; exiting SOCS importer');
        }

        if ('' === $this->token) {
            throw new DomainException('Missing SOCS endpoint token; exiting SOCS importer');
        }

        if ('' === $this->schoolId) {
            throw new DomainException('Missing SOCS school id; exiting SOCS importer');
        }

        $this->httpClient = new HttpClient($this->host, $this->schoolId, $this->token);

        $seasonMapper = SeasonMapper::getInstance();
        $sportMapper = SportMapper::getInstance();
        $divisionMapper = DivisionMapper::getInstance();
        $teamMapper = TeamMapper::getInstance();
        $venueMapper = VenueMapper::getInstance();
        $schoolMapper = SchoolMapper::getInstance();
        $matchupMapper = MatchupMapper::getInstance();
        $resultMapper = ResultMapper::getInstance();

        $bus = new GenericCollection(IStrategy::class);

        if ($fixtures) {
            $bus->add(new Fixtures\Prepare(
                $this->log,
                $this->perform,
                $this->lock,
                $matchupMapper
            ))
            ->add(new Fixtures\Import(
                $this->log,
                $this->perform,
                $this->lock,
                $this->httpClient,
                $seasonMapper,
                $sportMapper,
                $divisionMapper,
                $teamMapper,
                $venueMapper,
                $schoolMapper,
                $matchupMapper
            ))
            ->add(new Fixtures\Cleanup(
                $this->log,
                $this->perform,
                $this->lock,
                $matchupMapper
            ));
        }

        if ($results) {
            $bus->add(new Results\Prepare(
                $this->log,
                $this->perform,
                $this->lock,
                $resultMapper
            ))
            ->add(new Results\Import(
                $this->log,
                $this->perform,
                $this->lock,
                $this->httpClient,
                $matchupMapper,
                $resultMapper
            ))
            ->add(new Results\Cleanup(
                $this->log,
                $this->perform,
                $this->lock,
                $resultMapper
            ));
        }

        if ($teams) {
            $bus->add(new Teams\Prepare(
                $this->log,
                $this->perform,
                $this->lock,
                $seasonMapper,
                $divisionMapper,
                $teamMapper
            ))
            ->add(new Teams\Import(
                $this->log,
                $this->perform,
                $this->lock,
                $this->httpClient,
                $seasonMapper,
                $sportMapper,
                $divisionMapper,
                $teamMapper
            ))
            ->add(new Teams\Cleanup(
                $this->log,
                $this->perform,
                $this->lock,
                $divisionMapper,
                $teamMapper
            ));
        }

        // Always log API usage statistics at the end
        $bus->add(new ApiStatistics(
            $this->log,
            $this->httpClient
        ));

        return $bus;
    }
}
