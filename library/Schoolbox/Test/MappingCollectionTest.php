<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Test;

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Column;
use Schoolbox\IMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;
use PHPUnit\Framework;

class MappingCollectionTest extends TestCase
{
    // ==================================== Tests ====================================
    /**
     * @param IMapper $mapper
     * @param string  $column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnToPropertyMappingUses')]
    public function testColumnToPropertyMappingUses(IMapper $mapper, string $column): void
    {
        $this->assertNotNull($mapper->getMap()->getOffsetByColumn($column));
    }


    /**
     * @param IMapper $mapper
     * @param string  $column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnToPropertyMappingJoins')]
    public function testColumnToPropertyMappingJoins(IMapper $mapper, string $column): void
    {
        $this->assertNotNull($mapper->getMap()->getOffsetByColumn($column));
    }


    /**
     * @param IMapper $mapper
     * @param string  $column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnToPropertyMappingJoinsReverseSingle')]
    public function testColumnToPropertyMappingJoinsReverseSingle(IMapper $mapper, string $column): void
    {
        $this->assertNotNull($mapper->getMap()->getOffsetByColumn($column));
    }


    /**
     * @param IMapper $mapper
     * @param string  $column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnToPropertyMappingJoinsReverseMulti')]
    public function testColumnToPropertyMappingJoinsReverseMulti(IMapper $mapper, string $column): void
    {
        $this->assertNotNull($mapper->getMap()->getOffsetByColumn($column));
    }


    /**
     * @param IMapper $mapper
     * @param string  $column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnToPropertyMappingJoinsVia')]
    public function testColumnToPropertyMappingJoinsVia(IMapper $mapper, string $column): void
    {
        $this->assertNotNull($mapper->getMap()->getOffsetByColumn($column));
    }


    /**
     * @param IMapper $mapper
     * @param string  $column
     * @param string  $hydratorClass
     * @throws Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnToPropertyMappingRecursive')]
    public function testColumnToPropertyMappingRecursive(IMapper $mapper, string $column, string $hydratorClass): void
    {
        $this->assertNotNull($mapper->getMap()->getOffsetByColumn($column));
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInstanceOf($hydratorClass, $mapper->getColumnScalarHydrator($column));
    }

    // ==================================== Data Providers ====================================


    /**
     * @return array
     */
    public static function providerColumnToPropertyMappingUses(): array
    {
        $data = [];

        $mapper1 = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('a_column', Mapper::TYPE_INTEGER);
            }
        };
        $data[]  = [$mapper1, 'a_column'];

        $mapper2 = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('a_column', Mapper::TYPE_INTEGER, null, 'aProperty');
            }
        };
        $data[]  = [$mapper2, 'a_column'];

        $mapper3 = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('a_column', Mapper::TYPE_STRING);
            }
        };
        $data[]  = [$mapper3, 'a_column'];

        return $data;
    }


    /**
     * @return array
     */
    public static function providerColumnToPropertyMappingJoins(): array
    {
        $mapper = new class extends Mapper {
            protected function mapping(): void
            {
                $this->joins('user_id', UserMapper::class, 'id', null, null, 'user');
            }
        };

        return [
            [$mapper, 'user_id'],
        ];
    }


    /**
     * @return array
     */
    public static function providerColumnToPropertyMappingJoinsReverseSingle(): array
    {
        $mapper = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('id', Mapper::TYPE_INTEGER, Mapper::COLUMN_IDENTIFIER);
                $this->joinsReverseSingle('assessment_id', SubmissionBoxMapper::class, 'duework', 'duework');
            }
        };

        return [
            [$mapper, 'duework.' . SubmissionBoxMapper::getInstance()->getIdentifierColumn()],
        ];
    }


    /**
     * @return array
     */
    public static function providerColumnToPropertyMappingJoinsReverseMulti(): array
    {
        $mapper = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('id', Mapper::TYPE_INTEGER, Mapper::COLUMN_IDENTIFIER);
                $this->joinsReverseMulti('assessment_id', SubmissionBoxMapper::class, 'duework', 'duework');
            }
        };

        return [
            [$mapper, 'duework.' . SubmissionBoxMapper::getInstance()->getIdentifierColumn()],
        ];
    }


    /**
     * @return array
     */
    public static function providerColumnToPropertyMappingJoinsVia(): array
    {
        $mapper = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('id', Mapper::TYPE_INTEGER, Mapper::COLUMN_IDENTIFIER);
                $this->joinsVia('userCampus', CampusMapper::class, 'id', 'campus', 'uid', 'id', null, null, 'campusCollection');
            }
        };

        return [
            [$mapper, 'userCampus.campus'],
        ];
    }


    /**
     * @return array
     */
    public static function providerColumnToPropertyMappingRecursive(): array
    {
        // t1.id <= t2.t1_id <= t3.t2_t1_id

        $mapper1 = new class extends Mapper {
            protected function mapping(): void
            {
                $this->uses('id', Mapper::TYPE_INTEGER, Mapper::COLUMN_IDENTIFIER);
            }
        };

        $mapper2 = new class ($mapper1) extends Mapper {
            private $mapper1;


            public function __construct(Mapper $mapper1)
            {
                $this->mapper1 = $mapper1;
                parent::__construct();
            }


            protected function mapping(): void
            {
                $this->joins('t1_id', $this->mapper1);
            }
        };

        $mapper3 = new class ($mapper2) extends Mapper {
            private $mapper2;


            public function __construct(Mapper $mapper2)
            {
                $this->mapper2 = $mapper2;
                parent::__construct();
            }


            protected function mapping(): void
            {
                $this->joins('t2_t1_id', $this->mapper2, 't1_id');
            }
        };

        return [
            [$mapper3, 't2_t1_id', Column\Integer::class],
        ];
    }
}
