<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Codec\ICodec;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByType;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;

class Factory
{
    public const STUDENT_BY_TYPE  = 'studentByType';
//    public const TYPE_BY_MONTH    = 'typeByMonth';
//    public const TYPE_BY_WEEK     = 'typeByWeek';
//    public const STUDENT_BY_MONTH = 'studentByMonth';
//    public const STUDENT_BY_WEEK  = 'studentByWeek';

    public function __construct(
        private ModelToArray $userTranslator,
        private UserMapper $userMapper,
        private PastoralMapper $pastoralMapper,
        private string $resultType,
        private ?string $orderBy,
        private ?string $order,
        private int $limit = Cursor::LIMIT_DEFAULT,
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
                $this->orderBy,
                $this->order,
                $this->limit,
            ),
            default => null
        };
    }
}
