<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Grades;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;

class Controller extends Base
{
    private const PAGE_CURRENT = 'grades';


    /**
     * @var GradeGroupMapper
     */
    private $mapper;


    /**
     * Constructor
     *
     * @param GradeGroupMapper $mapper
     */
    public function __construct(GradeGroupMapper $mapper)
    {
        $this->mapper = $mapper;
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
                    'gradeGroups' => $this->mapper->findAll()->map(new Translator\GradeGroup()),
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
