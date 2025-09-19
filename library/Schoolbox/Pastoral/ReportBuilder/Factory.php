<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray as PastoralTranslator;
use Schoolbox\Pastoral\ReportBuilder\TabType\RecordList;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByMonth;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByType;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByWeek;
use Schoolbox\Pastoral\ReportBuilder\TabType\TypeByMonth;
use Schoolbox\Pastoral\ReportBuilder\TabType\TypeByWeek;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;

class Factory
{
    public const STUDENT_BY_TYPE  = 'studentByType';
    public const TYPE_BY_MONTH    = 'typeByMonth';
    public const TYPE_BY_WEEK     = 'typeByWeek';
    public const STUDENT_BY_MONTH = 'studentByMonth';
    public const STUDENT_BY_WEEK  = 'studentByWeek';
    public const RECORD_LIST = 'list';

    public function __construct(
        private ModelToArray $userTranslator,
        private UserMapper $userMapper,
        private PastoralMapper $pastoralMapper,
        private PastoralTranslator $pastoralTranslator,
        private string $resultType,
        private ?string $orderBy,
        private ?string $order,
        private ?int $limit = Base::LIMIT_DEFAULT,
    ) {
    }

    public function produce(
        Helper $helper,
        string $type,
    ) {
        return match ($type) {
            self::STUDENT_BY_TYPE => new StudentByType(
                $helper,
                $this->userTranslator,
                $this->userMapper,
                $this->pastoralMapper,
                $this->resultType,
                $this->orderBy ?? Base::SORT_BY_STUDENT_DEFAULT,
                $this->order,
                $this->limit,
            ),
            self::TYPE_BY_MONTH => new TypeByMonth(
                $helper,
                $this->userTranslator,
                $this->userMapper,
                $this->pastoralMapper,
                $this->resultType,
                $this->orderBy ?? Base::SORT_BY_TYPE_DEFAULT,
                $this->order,
                $this->limit,
            ),
            self::TYPE_BY_WEEK => new TypeByWeek(
                $helper,
                $this->userTranslator,
                $this->userMapper,
                $this->pastoralMapper,
                $this->resultType,
                $this->orderBy ?? Base::SORT_BY_TYPE_DEFAULT,
                $this->order,
                $this->limit,
            ),
            self::STUDENT_BY_MONTH => new StudentByMonth(
                $helper,
                $this->userTranslator,
                $this->userMapper,
                $this->pastoralMapper,
                $this->resultType,
                $this->orderBy ?? Base::SORT_BY_STUDENT_DEFAULT,
                $this->order,
                $this->limit,
            ),
            self::STUDENT_BY_WEEK => new StudentByWeek(
                $helper,
                $this->userTranslator,
                $this->userMapper,
                $this->pastoralMapper,
                $this->resultType,
                $this->orderBy ?? Base::SORT_BY_STUDENT_DEFAULT,
                $this->order,
                $this->limit,
            ),
            self::RECORD_LIST => new RecordList(
                $helper,
                $this->userTranslator,
                $this->userMapper,
                $this->pastoralMapper,
                $this->pastoralTranslator,
                $this->resultType,
                $this->limit,
            ),
            default => null
        };
    }
}
