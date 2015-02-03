<?php
/**
 * Factory for creating tutor group factory objects
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
    /**
     * @var Gateway
     */
    protected $gateway;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var CategoryMapper
     */
    protected $categoryMapper;

    /**
     * @var Grades
     */
    protected $gradesService;

    /**
     * Constructor
     *
     * @param Gateway        $gateway
     * @param UserMapper     $userMapper
     * @param CategoryMapper $categoryMapper
     * @param Grades         $gradesService
     */
    public function __construct(
        Gateway $gateway,
        UserMapper $userMapper,
        CategoryMapper $categoryMapper,
        Grades $gradesService
    ) {
        // Set up required mappers, DB access, etc. here.
        $this
            ->setGateway($gateway)
            ->setUserMapper($userMapper)
            ->setCategoryMapper($categoryMapper)
            ->setGradesService($gradesService)
        ;
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
        $tutorGroup = new TutorGroup();
        $tutorGroup
            ->setStudent($student)
            ->setYear($year)
            ->setActivities($this->getGateway()->getAllActivities($year, $student))
            ->setAttendances($this->getGateway()->getAllAttendances($year, $student))
            ->setTutors($this->getGateway()->getAllTutors($year, $student))
        ;
        // also get the folder, grades and subjects
        return $tutorGroup;
    }

    /**
     * @return Gateway
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @param  Gateway $gateway
     *
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     *
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return CategoryMapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param  CategoryMapper $categoryMapper
     *
     * @return $this
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }

    /**
     * @return Grades
     */
    public function getGradesService()
    {
        return $this->gradesService;
    }

    /**
     * @param  Grades $gradesService
     *
     * @return $this
     */
    public function setGradesService($gradesService)
    {
        $this->gradesService = $gradesService;
        return $this;
    }
}
