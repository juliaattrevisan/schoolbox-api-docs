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
        private TermMapper $termMapper,
        private PastoralMapper $pastoralMapper,
        private string $resultType,
        private ?int $limit = null,
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
                $this->termMapper,
                $this->pastoralMapper,
                $this->resultType,
                $this->limit,
            ),
            default => null
        };
    }
}
