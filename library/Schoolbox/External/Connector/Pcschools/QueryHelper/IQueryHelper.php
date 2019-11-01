<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * @param string $studentExternalId
     * @return array
     */
    public function getGetStudentQuery($studentExternalId): array;

    /**
     * @param array $rows
     * @return array
     */
    public function processGetStudentResult(array $rows): array;

    /**
     * @param array $studentExternalId
     * @return array
     */
    public function getGetStudentContactListQuery(array $studentExternalId): array;

    /**
     * @param array $studentExternalIds
     * @return array
     */
    public function getGetParentEmailsQuery($studentExternalIds): array;

    /**
     * @param string $externalUserId
     * @return array
     */
    public function getGetEnrolledStudentsQuery($externalUserId): array;

}
