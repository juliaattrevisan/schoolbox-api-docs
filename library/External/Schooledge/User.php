<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;

/**
 * Schooledge implementation of user retrieve methods.
 */
class External_Schooledge_User extends External_Humanedge_User implements External_UserInterface
{
    /**
     * Returns list of subjects the user is enrolled in
     *
     * @param User $user
     * @return array
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        $quotedId    = $this->getDatabase()->smartQuote($externalId);
        $campusCodes = array_map(
            [$this->getDatabase(), 'smartQuote'],
            $user->getCampusCollection()->getColumnValues('extTimetableCode')
        );
        if (empty($campusCodes)) {
            return [];
        }
        $campusCodeString = implode(',', $campusCodes);
        if ($user->getRole()->isStudent()) {
            // 1st part: blocked classes
            // 2nd part: non-blocked classes
            $query = "
                SELECT DISTINCT
                    tc.Name     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType
                FROM fc.TimetableSubject ts

                INNER JOIN fc.Timetable              t   ON ts.TimetableID = t.ID
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                INNER JOIN fc.TimetableSubjectChoice tsc  ON tc.ID             = tsc.TimetableClassID
                INNER JOIN dbo.Student               s    ON tsc.PersonID      = s.PersonID
                
                WHERE s.Code = $quotedId         
                AND   t.Enabled = 1
                AND   t.Name IN ($campusCodeString)

                UNION

                SELECT DISTINCT
                    tc.Name     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType

                FROM fc.TimetableSubject ts

                INNER JOIN fc.TimetableClass      tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.YearLevelSub        yls  ON tc.YearLevelSubID = yls.ID
                INNER JOIN fc.YearLevelSubStudent ylss ON yls.ID            = ylss.YearLevelSubID
                INNER JOIN dbo.Student            s    ON ylss.PersonID     = s.PersonID
                INNER JOIN fc.Timetable           t    ON ts.TimetableID    = t.ID

                WHERE s.Code = $quotedId
                AND   t.Enabled = 1
                AND   t.Name IN ($campusCodeString)

                ORDER BY code ASC

            ";
        } elseif ($user->getRole()->isStaff()) {
            // As far as I can interpret:
            // Use base staff if there is no TimetableClassStaff in TimetableClassInstanceStaff
            // Use use replacement staff if it's found
            $query = "
                SELECT DISTINCT
                    tc.Name     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType
                FROM fc.TimetableSubject ts

                -- Base join to timetable
                INNER JOIN fc.Timetable t ON ts.TimetableID = t.ID

                -- Base join to class
                INNER JOIN fc.TimetableClass tc ON ts.ID = tc.TimetableSubjectID

                -- Base staff
                LEFT  JOIN fc.TimetableClassStaff tcs        ON tc.ID        = tcs.TimetableClassId
                LEFT  JOIN dbo.Staff              staff_base ON tcs.PersonID = staff_base.PersonID

                -- Override staff
                LEFT  JOIN fc.TimetableClassInstance      tci               ON tc.ID         = tci.TimetableClassID
                LEFT  JOIN fc.TimetableClassInstanceStaff tcis              ON tci.ID        = tcis.TimetableClassInstanceId
                LEFT  JOIN dbo.Staff                      staff_replacement ON tcis.PersonID = staff_replacement.PersonID

                WHERE (
                    (staff_base.Code = $quotedId AND tcis.TimetableClassStaffID IS NULL)
                    OR
                    staff_replacement.Code = $quotedId
                )
                AND t.Enabled = 1
                AND t.Name IN ($campusCodeString)

                ORDER BY code ASC
            ";
        } else {
            return [];
        }
        return $this->getDatabase()->getAll($query);
    }
}
