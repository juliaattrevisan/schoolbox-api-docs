<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Assessment\Grades;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class TutorGroupFactory
{
    public function __construct(
        Gateway $gateway,
        UserMapper $userMapper,
        CategoryMapper $categoryMapper,
        Grades $gradesService
    ) {
        // Set up required mappers, DB access, etc. here.
    }

    /**
     * Builds a TutorGroup object
     *
     * @param int  $year
     * @param User $student
     *
     * @return TutorGroup
     */
    public function build($year, User $student)
    {
        //
    }
}
