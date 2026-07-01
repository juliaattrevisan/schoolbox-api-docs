<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Instance\Model as RubricInstance;
use Schoolbox\Learning\Assessment\DataModel\Mapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;

#[CoversClass(MapperProxy::class)]
class MapperProxyRubricPersistenceTest extends TestCase
{
    public function testPersistedRubricInstanceGridIsLoadedWhenMissingFromTransientSaveState(): void
    {
        $assessment     = (new DueWork())->setId(123);
        $rubricInstance = (new RubricInstance())->setId(456);
        $grid           = $this->createStub(Grid::class);

        $rubricMapper = $this->createMock(RubricDataSource::class);
        $rubricMapper
            ->expects($this->once())
            ->method('retrieveOneGrid')
            ->with($assessment, $rubricInstance, false)
            ->willReturn($grid);

        $mapperProxy = $this->buildMapperProxy($rubricMapper);

        $resolvedGrid = $this->invokeResolveGridForPersistence($mapperProxy, $assessment, $rubricInstance);

        $this->assertSame($grid, $resolvedGrid);
        $this->assertSame($grid, $rubricInstance->getGrid());
    }

    public function testNewRubricInstanceWithoutGridCannotBePersisted(): void
    {
        $assessment     = (new DueWork())->setId(123);
        $rubricInstance = (new RubricInstance())->setId(-1);
        $rubricMapper   = $this->createMock(RubricDataSource::class);
        $rubricMapper
            ->expects($this->never())
            ->method('retrieveOneGrid');

        $mapperProxy = $this->buildMapperProxy($rubricMapper);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot persist a new rubric instance without a prepared grid.');

        $this->invokeResolveGridForPersistence($mapperProxy, $assessment, $rubricInstance);
    }

    private function buildMapperProxy(RubricDataSource $rubricMapper): MapperProxy
    {
        return new MapperProxy(
            $this->createStub(Mapper::class),
            $this->createStub(Translator::class),
            $this->createStub(ParticipantDataSource::class),
            $rubricMapper,
        );
    }

    private function invokeResolveGridForPersistence(
        MapperProxy $mapperProxy,
        DueWork $assessment,
        RubricInstance $rubricInstance,
    ): Grid {
        $method = new ReflectionMethod($mapperProxy, 'resolveRubricGridForPersistence');
        $method->setAccessible(true);

        return $method->invoke($mapperProxy, $assessment, $rubricInstance);
    }
}
