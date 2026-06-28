<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Season\Model as SeasonModel;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Socs\Fixtures\Import as FixturesImport;
use Schoolbox\Socs\Fixtures\Lookup\Person;
use Schoolbox\Socs\Fixtures\Registry;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\Results\Import as ResultsImport;
use Schoolbox\Socs\Teams\Import as TeamsImport;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\LockInterface;

#[CoversClass(FixturesImport::class)]
#[CoversClass(ResultsImport::class)]
#[CoversClass(TeamsImport::class)]
class ImportPathTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../Fixtures/Import.php';
        require_once __DIR__ . '/../Results/Import.php';
        require_once __DIR__ . '/../Teams/Import.php';
    }

    public function testFixturesImportUsesSchoolXmlPath(): void
    {
        $httpClient = $this->expectGetPath('/school/xml/fixturecalendar.ashx', '<fixtures />');
        $registry = $this->createRegistry();
        $person = $this->createStub(Person::class);

        $import = new FixturesImport(
            new NullLogger(),
            false,
            $this->createStub(LockInterface::class),
            $httpClient,
            $registry,
            $this->createStub(MatchupMapper::class),
            $this->createStub(UserMapper::class),
            $this->createStub(TeamPlayerMapper::class),
            $this->createStub(TeamCoachMapper::class),
            $this->createStub(CoachMapper::class),
            false,
            $person,
        );

        $import->import();
    }

    public function testResultsImportUsesSchoolXmlPath(): void
    {
        $httpClient = $this->expectGetPath('/school/xml/results.ashx', '<results />');

        $import = new ResultsImport(
            new NullLogger(),
            false,
            $this->createStub(LockInterface::class),
            $httpClient,
            $this->createStub(MatchupMapper::class),
            $this->createStub(ResultMapper::class),
        );

        $import->import();
    }

    public function testTeamsImportUsesSchoolXmlPath(): void
    {
        $httpClient = $this->expectGetPath('/school/xml/mso-sport.ashx', '<sports />');
        $registry = $this->createRegistry();

        $import = new TeamsImport(
            new NullLogger(),
            false,
            $this->createStub(LockInterface::class),
            $httpClient,
            $registry,
        );

        $import->import();
    }

    private function expectGetPath(string $path, string $xml): HttpClient
    {
        $httpClient = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();

        $httpClient
            ->expects($this->once())
            ->method('get')
            ->with($path, $this->isArray())
            ->willReturn(new \SimpleXMLElement($xml));

        return $httpClient;
    }

    private function createRegistry(): Registry
    {
        $registry = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['recallSeason'])
            ->getMock();

        $registry
            ->expects($this->once())
            ->method('recallSeason')
            ->willReturn($this->createStub(SeasonModel::class));

        return $registry;
    }
}
