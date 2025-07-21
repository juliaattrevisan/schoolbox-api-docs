<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByType;
use Schoolbox\User\Translator\Author\ModelToArray;

class Factory
{
    public const STUDENT_BY_TYPE  = 'studentByType';
//    public const TYPE_BY_MONTH    = 'typeByMonth';
//    public const TYPE_BY_WEEK     = 'typeByWeek';
//    public const STUDENT_BY_MONTH = 'studentByMonth';
//    public const STUDENT_BY_WEEK  = 'studentByWeek';

    public function __construct(
        private ModelToArray $userTranslator,
    ) {
    }

    public function produce(
        PastoralReport $report,
        string $type,
    ) {
        return match ($type) {
            self::STUDENT_BY_TYPE => new StudentByType($report, $this->userTranslator),
            default => null
        };
    }
}
