<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace External\Pcschools\QueryHelper;

interface IQueryHelper
{
    public const FREEDOM_DETECT = "SELECT COUNT(*) FROM pcschool.dbo.workarea WHERE DBNAME = 'PCSchool' AND STATUS = 'F'";

    /**
     * @return array
     */
    public function getFindAllParentsQuery(): array;

//    /**
//     * @return array
//     */
//    public function getGetStudentQuery(): array;
//
//    /**
//     * @return array
//     */
//    public function getGetStudentContactListQuery(): array;
//
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