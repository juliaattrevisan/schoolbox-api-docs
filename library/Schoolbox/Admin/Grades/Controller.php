<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Grades;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeLevelMapper;

class Controller extends Base
{
    private const PAGE_CURRENT = 'grades';


    /**
     * @var GradeLevelMapper
     */
    private $gradeLevelMapper;


    /**
     * @var GradeLevelGroupMapper
     */
    private $gradeLevelGroupMapper;


    /**
     * Constructor
     *
     * @param GradeLevelMapper      $gradeLevelMapper
     * @param GradeLevelGroupMapper $gradeLevelGroupMapper
     */
    public function __construct(GradeLevelMapper $gradeLevelMapper, GradeLevelGroupMapper $gradeLevelGroupMapper)
    {
        $this->gradeLevelMapper      = $gradeLevelMapper;
        $this->gradeLevelGroupMapper = $gradeLevelGroupMapper;
    }


    /**
     * Get index for modify grades form.
     *
     * @api   GET /adminv2/grades
     * @since 20.2
     */
    public function getIndex(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminGrades/index.twig', [
                    'pageCurrent' => self::PAGE_CURRENT,
                    'gradeGroups' => $this->gradeLevelGroupMapper->findAll()->map(new Translator\GradeGroup()),
                ]
            )
        );
    }


    /**
     * Modify admin grades.
     *
     * @api   POST /adminv2/grades
     * @since 20.2
     */
    public function postSaveGrades(): void
    {
        // do something
    }
}
