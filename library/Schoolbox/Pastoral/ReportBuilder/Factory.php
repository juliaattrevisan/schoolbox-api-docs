<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByType;
use Schoolbox\User\Translator\Author\ModelToArray;

class Factory
{
    public const STUDENT_BY_TYPE  = 'studentByType';
//    public const TYPE_BY_MONTH    = 'TypeByMonth';
//    public const TYPE_BY_WEEK     = 'TypeByWeek';
//    public const STUDENT_BY_MONTH = 'studentByMonth';
//    public const STUDENT_BY_WEEK  = 'studentByWeek';

    public function __construct(
        private ModelToArray $userTranslator,
    ) {
    }

    public function produce(
        string $type,
    ) {
        return match ($type) {
            self::STUDENT_BY_TYPE => new StudentByType($this->userTranslator),
            default => null
        };
    }
}
