<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\QueryHelper;

interface IQueryHelper
{
    public const FREEDOM_DETECT = "SELECT COUNT(*) FROM pcschool.dbo.workarea WHERE DBNAME = 'PCSchool' AND STATUS = 'F'";

    /**
     * @return array
     */
    public function getFindAllParentsQuery(): array;

    /**
     * @param int $studentExternalId
     * @return array
     */
    public function getGetStudentQuery($studentExternalId): array;

    /**
     * @param array $rows
     * @return array
     */
    public function processGetStudentResult(array $rows): array;

    /**
     * @param int $studentExternalId
     * @return array
     */
    public function getGetStudentContactListQuery($studentExternalId): array;

    /**
     * @param array $studentExternalIds
     * @return array
     */
    public function getGetParentEmailsQuery($studentExternalIds): array;

    /**
     * @param int $externalUserId
     * @return array
     */
    public function getGetEnrolledStudentsQuery($externalUserId): array;

}