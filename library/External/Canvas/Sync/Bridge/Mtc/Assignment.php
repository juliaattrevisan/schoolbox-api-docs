<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Mtc;

use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Mapper\Exception as MapperException;


class Assignment extends \External\Canvas\Sync\Bridge\Assignment
{
    /** @var null|WorkType\Model */
    private $quizWorkType;

    /**
     * Returns the work type to assign to this quiz type
     *
     * @param string $quizType
     * @return null|WorkType\Model
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        if (!isset($this->quizWorkType)) {
            try {
                $this->quizWorkType = $this->workTypeMapper->findOneByColumn('name', 'Quiz');
            } catch (MapperException $e) {
                $this->quizWorkType = null;
            }
        }
        return $this->quizWorkType;
    }
}
