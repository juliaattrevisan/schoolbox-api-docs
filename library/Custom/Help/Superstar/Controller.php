<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Web\Controller\Base;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * The name of this report which appears when you bookmark it
     * in yer browser
     */
    private const REPORT_TITLE = 'OLC Superstar Report';


    /**
     * We only care about the Unit Specs in this particular course
     * TODO Potentially made configurable in a ui somehwere
     */
    private const COURSE_ID = 34;


    /**
     * Controller constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * Show a list of people who could potentially be awarded Star or Superstar
     * badges for accomplishing 99% and 100% respectively, in their Units
     *
     * @throws Error
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getCandidates(): void
    {
        /* Get all the data ready
         */
        $statement = $this->generateCandidatesStatement()->execute([
            'courseId' => self::COURSE_ID
        ]);

        /* Start preprocessing each guy in the result set,
         * analysing each row's norm_mark to find out what award should be given
         * (if any) when we're done with each guy's unit.
         *
         * Collate those outcomes into a mega struct for frontend
         */


        $this->response = $this->responseFromTemplate('@superstar/index.twig', [
            'report' => [
                'title' => self::REPORT_TITLE,
            ]
        ], self::REPORT_TITLE);
    }


    /**
     * Prepare a statement for award candidates
     *
     * @return \PDOStatement
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function generateCandidatesStatement(): \PDOStatement
    {
        $query = "
            SELECT 
                cui.course_unit_specification_id unit_spec_id,
                cui.title AS unit_title,
                u.id AS user_id,
                u.firstname AS user_firstname,
                u.lastname AS user_lastname,
                prc.id,
                prc.norm_mark
            FROM course_implementation ci
            INNER JOIN course_unit_implementation cui on ci.id = cui.course_implementation_id
            INNER JOIN assessment_course_unit_implementation acui ON cui.id = acui.course_unit_implementation_id
            INNER JOIN project p ON acui.assessment_id = p.assessment_id
            INNER JOIN project_result prc ON p.id = prc.project_id
            INNER JOIN `user` u ON prc.user_id = u.id
            WHERE ci.course_specification_id = :courseId
            ORDER BY unit_title, unit_spec_id, user_lastname
        ";

        return $this->getDi()->get('databaseInternal')->prepare($query);
    }


    /**
     * Award a badge to a particular person
     */
    public function postAward(): void
    {
    }
}
