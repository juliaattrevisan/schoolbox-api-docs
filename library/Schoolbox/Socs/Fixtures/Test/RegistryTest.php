<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Sport\Model as SportModel;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Socs\Fixtures\Registry;

#[CoversClass(Registry::class)]
class RegistryTest extends TestCase
{
    public function testRecallsSameVisibleSportNameByDistinctSourceIdentities(): void
    {
        $sportMapper = $this->getMockBuilder(SportMapper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneByColumn', 'createModel'])
            ->getMock();
        $sportMapper
            ->expects($this->exactly(2))
            ->method('findOneByColumn')
            ->willReturnCallback(static function (string $column, string $value): never {
                self::assertSame('external_id', $column);
                self::assertContains(
                    $value,
                    [
                        'socs-co-curricular:club:101',
                        'socs-co-curricular:club:202',
                    ],
                );

                throw new Exception();
            });
        $sportMapper
            ->expects($this->exactly(2))
            ->method('createModel')
            ->willReturnCallback(static fn (): SportModel => new SportModel());

        $registry = new Registry(
            new NullLogger(),
            false,
            $this->mapperMock(SeasonMapper::class),
            $sportMapper,
            $this->mapperMock(DivisionMapper::class),
            $this->mapperMock(TeamMapper::class),
            $this->mapperMock(VenueMapper::class),
            $this->mapperMock(SchoolMapper::class),
            'socs-co-curricular:',
            'SOCS Co-Curricular',
        );

        $first = $registry->recallSport('Chess - Senior Activities', 'club:101');
        $second = $registry->recallSport('Chess - Senior Activities', 'club:202');

        self::assertNotSame($first, $second);
        self::assertSame('Chess - Senior Activities', $first->getName());
        self::assertSame('Chess - Senior Activities', $second->getName());
        self::assertSame('socs-co-curricular:club:101', $first->getExternalId());
        self::assertSame('socs-co-curricular:club:202', $second->getExternalId());
    }

    /**
     * Create an uninitialized mapper mock for an unused Registry dependency.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function mapperMock(string $class): object
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
