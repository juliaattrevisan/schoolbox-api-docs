<?php
/**
 * Interface for how school management systems should determine user's detention.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

interface External_UserDetentionInterface {

    /**
     * Retrieves a user's awards.
     *
     * @param mixed $externalId The student's external ID.
     * @param int   $year
     * @param int   $semester
     *
     * @return array A list of records, each with the following fields:
     *               * ID:               the student's external ID
     *               * FileYear:         year the award was given
     *               * FileSemester:     semester the award was given
     *               * AwardCode:        internal code for the award
     *               * AwardDescription: a desc of the award
     *               * AwardDate:        awarded date in Y-m-d format
     *               * AwardAchievement: what the award is
     *               * AwardComment:     any comments from the awarder
     */
    public function getAwards($externalId, $year, $semester);

    /**
     * Retrieves a user's detentions.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array A list of records, each with the following fields:
     *               * ID:                   the student's external ID
     *               * DetentionDate:        detention date in Y-m-d format
     *               * DetentionDescription: why the student has detention
     *               * DetentionComment:     any comments from the teacher
     */
    public function getDetentions($externalId, DateTime $fromDate = null, DateTime $toDate = null);

    /**
     * Retrieves a user's absences that do not have an absence code.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array A list of records, each with the following fields:
     *               * ID:                  the student's external ID
     *               * EventDateTime:       the time the student was absent, in
     *                                      Y-m-d H:i:s format
     *               * AbsenceTypeCode:     internal code for absence
     *               * AbsenceDescription:  description for the internal code
     *               * ClassCode:           code for class the student missed
     *               * ClassCampus:         code for campus of class
     *               * ClassDescription:    description of class
     *               * AttendanceDayNumber: day number of missed class
     *               * AttendancePeriod:    period number of missed class
     *               * LateArrivalFlag:     was student late or totally absent?
     *               * LateArrivalTime:     if late, when arrived (H:i:s format)
     */
    public function getAbsences($externalId, DateTime $fromDate = null, DateTime $toDate = null);

}
