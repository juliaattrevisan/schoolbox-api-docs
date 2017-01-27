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

        $quotedDate = $this->getDatabase()->smartQuote(Alaress_Date_Format::now()->getDatabaseDate());
        $quotedId   = $this->getDatabase()->smartQuote($externalId);
        if ($user->getRole()->isStudent()) {
            $query = "
                SELECT DISTINCT
                    ts.Code     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType
                FROM fc.TimetableSubject ts

                INNER JOIN fc.TimetableSubjectChoice tsc ON ts.ID          = tsc.TimetableSubjectID
                INNER JOIN fc.Timetable              t   ON ts.TimetableID = t.ID
                INNER JOIN dbo.Student               s   ON tsc.PersonID   = s.ID

                WHERE s.Code = $quotedId
                AND   $quotedDate BETWEEN(CONVERT(date, t.StartDate), CONVERT(date, t.EndDate))

                ORDER BY code ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            $query = "
                SELECT DISTINCT
                    ts.Code     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType
                FROM fc.TimetableSubject ts

                -- Base join to class and timetable
                INNER JOIN fc.TimetableClass tc ON ts.ID          = tc.TimetableSubjectID
                INNER JOIN fc.Timetable      t  ON ts.TimetableID = t.ID

                -- Base relation to staff - is this required?
                LEFT  JOIN fc.TimetableClassStaff tcs       ON tc.ID        = tcs.TimetableClassId
                LEFT  JOIN dbo.Staff              tcs_staff ON tcs.PersonID = tcs_staff.ID

                -- Individual relation to staff
                INNER JOIN fc.TimetableClassInstance      tci        ON tc.ID         = tci.TimetableClassID
                LEFT  JOIN fc.TimetableClassInstanceStaff tcis       ON tci.ID        = tcis.TimetableClassInstanceId
                LEFT  JOIN dbo.Staff                      tcis_staff ON tcis.PersonID = tcis_staff.ID

                WHERE (tcs_staff.Code = $quotedId OR tcis_staff.Code = $quotedId)
                AND   $quotedDate BETWEEN(CONVERT(date, t.StartDate), CONVERT(date, t.EndDate))

                ORDER BY code ASC
            ";
        } else {
            return [];
        }
        return $this->getDatabase()->getAll($query);
    }
}
