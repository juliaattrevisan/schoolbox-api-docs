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

        $quotedId = $this->getDatabase()->smartQuote($externalId);
        if ($user->getRole()->isStudent()) {
            $query = "
                SELECT DISTINCT
                    ts.Code as code,
                    ts.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM fc.TimetableSubject ts
                INNER JOIN fc.TimetableSubjectChoice tsc on ts.ID = tsc.TimetableSubjectID
                INNER JOIN Student s on tsc.PersonID = s.ID

                WHERE s.Code = $quotedId
                ORDER BY code ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            $query = "
                SELECT DISTINCT
                    ts.Code as code,
                    ts.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM fc.TimetableSubject ts
                INNER JOIN fc.TimetableClass tc on ts.ID = tc.TimetableSubjectID

                -- Base relation - is this required?
                LEFT  JOIN fc.TimetableClassStaff tcs on tc.ID = tcs.TimetableClassId
                LEFT  JOIN Staff tcs_staff ON tcs.PersonID = tcs_staff.ID

                -- Individual relation
                INNER JOIN fc.TimetableClassInstance tci on tc.ID = tci.TimetableClassID
                LEFT  JOIN fc.TimetableClassInstanceStaff tcis on tci.ID = tcis.TimetableClassInstanceId
                LEFT  JOIN Staff tcis_staff ON tcis.PersonID = tcis_staff.ID

                WHERE (tcs_staff.Code = $quotedId OR tcis_staff.Code = $quotedId)
                ORDER BY code ASC
            ";
        } else {
            return [];
        }
        return $this->getDatabase()->getAll($query);
    }
}
