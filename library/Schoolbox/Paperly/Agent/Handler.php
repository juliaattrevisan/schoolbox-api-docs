<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use OutOfBoundsException;

/**
 * Handler for the various actions the Paperly agent may perform.
 */
class Handler
{
    /**
     * Constructor
     *
     * @param IConnector $externalDb
     */
    public function __construct(private IConnector $externalDb)
    {
    }

    /**
     * Handle request for all year levels.
     *
     * @param array $data `$data['code']` may be an array or CSV list of codes:
     *                    if set, return only levels which match these codes.
     * @return array
     */
    public function handleAllYearLevelsRequest(array $data): array
    {
        $yearLevelArr = [];
        $params       = [];
        $sql          = "SELECT DISTINCT Code, Description FROM luYearLevel";

        if (isset($data['code'])) {
            $codes        = is_array($data['code']) ? $data['code'] : explode(',', $data['code']);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql          .= " WHERE Code IN ($placeholders)";
            $params       = array_merge($params, $codes);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $yearLevelArr[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $yearLevelArr;
    }

    /**
     * Handle request for all classes.
     *
     * @param array $data `$data['code']` may be an array or CSV list of codes:
     *                    if set, return only classes which match these codes.
     * @return array
     */
    public function handleAllClassesRequest(array $data): array
    {
        $yearLevelArr = [];
        $params       = [];
        $sql          = "SELECT DISTINCT sc.ClassCode, sc.Description, scs.StaffID, CONCAT(c.Preferred, ' ', c.Surname) AS TeacherName, c.OccupEmail
        FROM SubjectClasses sc
        LEFT JOIN FileSemesters fs ON fs.fileyear = sc.FileYear AND fs.filesemester = sc.filesemester
        LEFT JOIN SubjectClassStaff scs ON scs.ClassCode = sc.ClassCode AND scs.FileYear = sc.FileYear AND scs.FileSemester = sc.FileSemester
        LEFT JOIN community c ON c.id = scs.StaffID
        WHERE fs.systemcurrentflag = 1
            AND sc.classcode <> ''
            AND sc.FileType = 'a'
            AND scs.staffid IS NOT NULL";

        if (isset($data['code'])) {
            $codes        = is_array($data['code']) ? $data['code'] : explode(',', $data['code']);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql          .= " AND sc.classcode IN ($placeholders)";
            $params       = array_merge($params, $codes);
        }

        $sql .= " ORDER BY sc.ClassCode";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $yearLevelArr[] = [
                "Code"        => $row["ClassCode"],
                "Description" => $row["Description"],
            ];
        }

        return $yearLevelArr;
    }

    /**
     * Handle request for all staff members.
     *
     * @param array $data `$data['id']` may be an array or CSV list of IDs:
     *                    if set, return only staff which match these IDs.
     *                    `$data['email']` may be an email address: if set,
     *                    return only this staff member.
     *                    `$data['year_level']` may be an array or CSV list of
     *                    year levels: if set, return only staff which teach
     *                    these year levels.
     * @return array
     */
    public function handleAllStaffsRequest(array $data): array
    {
        $stfArr = [];
        $params = [];

        $sql = "SELECT DISTINCT c.StaffID, c.StaffSurname, c.StaffPreferredName, c.StaffOccupEmail, c.ActiveFlag";

        if (isset($data['year_level'])) {
            $sql .= ", sc.NormalYearLevel";
        }

        $sql .= " FROM vstaff c";

        $sqlWhere    = " WHERE c.ActiveFlag = 1 AND c.ActiveFlag <> 0 ";
        $sqlWhereSet = false;

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= $sqlWhere;
            $sql          .= " AND c.StaffID IN ($placeholders)";
            $params       = array_merge($params, $ids);
            $sqlWhereSet  = true;
        }

        if (isset($data['email'])) {
            $sql         .= $sqlWhere;
            $sql         .= " AND c.StaffOccupEmail = ?";
            $params[]    = $data['email'];
            $sqlWhereSet = true;
        }

        if (isset($data['year_level'])) {
            $yls          = is_array($data['year_level']) ? $data['year_level'] : explode(',', $data['year_level']);
            $placeholders = implode(',', array_fill(0, count($yls), '?'));
            $params       = array_merge($params, $yls);

            $sql         .= " LEFT JOIN timetable tt ON c.StaffID = tt.StaffID";
            $sql         .= " LEFT JOIN filesemesters fs ON fs.fileyear = tt.fileyear AND fs.FileSemester = tt.FileSemester";
            $sql         .= " LEFT JOIN SubjectClasses sc ON sc.FileYear = fs.FileYear AND sc.FileSemester = fs.FileSemester AND sc.ClassCode = tt.ClassCode";
            $sql         .= $sqlWhere;
            $sql         .= " AND fs.SystemCurrentFlag = 1 AND tt.filetype = 'a' AND tt.staffid <> 0  AND sc.NormalYearLevel IN ($placeholders)";
            $sqlWhereSet = true;
        }

        if (!$sqlWhereSet) {
            $sql .= $sqlWhere;
        }
        $sql .= " ORDER BY c.StaffSurname";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $stfArr[] = [
                "ID"            => $row["StaffID"],
                "Preferred"     => $row["StaffPreferredName"],
                "Surname"       => $row["StaffSurname"],
                "Email"         => $row["StaffOccupEmail"],
                "ActiveFlag"    => $row["ActiveFlag"],
                'YearLevelCode' => $row["NormalYearLevel"] ?? null,
            ];
        }

        return $stfArr;
    }

    /**
     * Handle request for all students.
     *
     * @param array $data `$data['id']` may be an array or CSV list of IDs:
     *                    if set, return only students which match these IDs.
     *                    `$data['email']` may be an email address: if set,
     *                    return only this student.
     *                    `$data['year_level']` may be an array or CSV list of
     *                    year levels: if set, return only students within these
     *                    year levels.
     *                    `$data['class']` may be an array or CSV list of
     *                    classes: if set, return only students within these
     *                    classes.
     * @return array
     */
    public function handleAllStudentsRequest(array $data): array
    {
        $stuArr = [];
        $params = [];
        $sql    = "WITH ContactPreference AS (
                SELECT
                    scd.ID,
                    MAX(CASE WHEN scd.ContactType = 'sct' THEN 1 ELSE 0 END) AS HasSCT,
                    MAX(CASE WHEN scd.ContactType = 'SC1' THEN 1 ELSE 0 END) AS HasSC1
                FROM pvStudentContactDetails scd
                GROUP BY scd.ID
            )
            SELECT DISTINCT s.StudentID, s.StudentSurname, s.StudentPreferred, s.StudentEmail, s.StudentGender,
                CAST(s.StudentBirthDate AS date) AS StudentBirthDate,
                s.StudentYearLevel, s.StudentYearLevelDescription, s.StudentBoarder, s.StudentTutor, s.StudentHouse, s.StudentHouseDescription, scd.ContactID,
                c1.Title, c1.Surname, c1.Preferred, c1.MobilePhone, c1.email, c1.occupemail,
                scd.DefaultEmail, scd.ContactSpouseID,
                c2.Title AS SpouseTitle, c2.Surname AS SpouseSurname,
                c2.Preferred AS SpousePreferred, c2.MobilePhone AS SpouseMobile,
                c2.email AS SpouseDefaultEmail, c2.occupemail AS SpouseOccupEmail
            FROM vstudents s
            LEFT JOIN FileSemesters fs ON fs.fileyear = s.fileyear AND fs.FileSemester = s.FileSemester
            LEFT JOIN vStudentClasses sc ON sc.StudentID = s.StudentID AND sc.fileyear = fs.fileyear AND sc.FileSemester = fs.FileSemester
            LEFT JOIN pvStudentContactDetails scd ON scd.ID = s.StudentID
            LEFT JOIN ContactPreference cp ON cp.ID = s.StudentID
            LEFT JOIN vStudentContactsLivesWith sclw ON sclw.id = s.studentid
            LEFT JOIN community c1 ON c1.id = sclw.Contact1ID
            LEFT JOIN community c2 ON c2.id = sclw.Contact2ID
            WHERE fs.systemcurrentflag = 1
            AND (
                (cp.HasSCT = 1 AND scd.ContactType = 'sct') OR
                (cp.HasSCT = 0 AND cp.HasSC1 = 1 AND scd.ContactType = 'SC1')
            )";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND S.StudentID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        if (isset($data['email'])) {
            $sql      .= " AND S.StudentEmail = ?";
            $params[] = $data['email'];
        }

        if (isset($data['year_level'])) {
            $yls          = is_array($data['year_level']) ? $data['year_level'] : explode(',', $data['year_level']);
            $placeholders = implode(',', array_fill(0, count($yls), '?'));
            $sql          .= " AND S.StudentYearLevel IN ($placeholders)";
            $params       = array_merge($params, $yls);
        }

        if (isset($data['class'])) {
            $classes      = is_array($data['class']) ? $data['class'] : explode(',', $data['class']);
            $placeholders = implode(',', array_fill(0, count($classes), '?'));
            $sql          .= " AND sc.ClassCode IN ($placeholders)";
            $params       = array_merge($params, $classes);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $stuArr[] = [
                "ID"                   => $row["StudentID"],
                "Preferred"            => $row["StudentPreferred"],
                "Surname"              => $row["StudentSurname"],
                "YearLevelCode"        => $row["StudentYearLevel"],
                "YearLevelDescription" => $row["StudentYearLevelDescription"],
                "Boarder"              => $row["StudentBoarder"],
                "RollGroup"            => $row["StudentTutor"],
                "HouseCode"            => $row["StudentHouse"],
                "HouseDescription"     => $row["StudentHouseDescription"],
                "Gender"               => $row["StudentGender"],
                "Email"                => $row["StudentEmail"],
                "BirthDate"            => $row["StudentBirthDate"],
                "Contact1Surname"      => $row["Surname"],
                "Contact1Name"         => $row["Preferred"],
                "Contact1Mobile"       => $row["MobilePhone"],
                "Contact1Email"        => $row["DefaultEmail"],
                "Contact2Surname"      => $row["SpouseSurname"],
                "Contact2Name"         => $row["SpousePreferred"],
                "Contact2Mobile"       => $row["SpouseMobile"],
                "Contact2Email"        => $row["SpouseDefaultEmail"],
            ];
        }

        return $stuArr;
    }

    /**
     * Handle request for student avatars (profile pictures).
     *
     * @param array $data `$data['id']` may be an array or CSV list of IDs:
     *                    if set, return only avatars for these students.
     * @return array
     */
    public function handleGetStudentsAvatarRequest(array $data): array
    {
        $avatarArr = [];
        $params    = [];

        $sql = "SELECT DISTINCT ID, Photo FROM vPhotos";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " WHERE ID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $avatarArr[$row["ID"]] = [
                "ID"     => $row["ID"],
                "Avatar" => base64_encode($row["Photo"]),
            ];
        }

        return $avatarArr;
    }

    /**
     * Handle request for student medical data.
     *
     * @param array $data `$data['id']` may be an array or CSV list of IDs:
     *                    if set, return only medical data for these students.
     * @return array
     */
    public function handleGetStudentsMedicalRequest(array $data): array
    {
        $stuMedicalArr = [];
        $params        = [date("Y")];

        $sql = "SELECT DISTINCT smc.ID, smc.MedicalConditionCode, smc.MedicalConditionTypeDescription,
            smc.SeverityCode, smc.SeverityDescription, smc.ConditionDetails,
            smc.AsthmaCategoryDescription, smc.AsthmaAction,
            smc.EmergencyTreatmentDetails, smc.OtherTreatmentDetails
        FROM vStudentMedicalCondition smc
        LEFT JOIN FileSemesters fs ON fs.fileyear = smc.FileYear
        WHERE fs.fileyear = ? AND ActiveFlag = 1 AND smc.medicalconditioncode <> 'LEARNINGDISORDER'";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND smc.ID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $stuMedicalArr[$row["StudentID"]][] = [
                "ID"                                  => $row["StudentID"],
                "MedicalConditionCode"                => $row["MedicalConditionCode"],
                "MedicalConditionTypeDescription"     => $row["MedicalConditionTypeDescription"],
                "MedicalConditionSeverityCode"        => $row["SeverityCode"],
                "MedicalConditionSeverityDescription" => $row["SeverityDescription"],
                "MedicalConditionDetails"             => htmlentities($row["ConditionDetails"]),
                "EmergencyTreatmentDetails"           => $row["EmergencyTreatmentDetails"],
                "OtherTreatmentDetails"               => $row["OtherTreatmentDetails"],
            ];
        }

        return $stuMedicalArr;
    }

    /**
     * Handle request for fetching students for a parent.
     *
     * @param array $data `$data['id']` must be a parent ID.
     * @return array
     * @throws OutOfBoundsException if `$data['id']` is not a parent ID.
     */
    public function handleGetStudentsFromParentRequest(array $data): array
    {
        $id = $data['id'] ?? null;
        if (!isset($id)) {
            throw new OutOfBoundsException('Parent ID not provided');
        }

        $stuArr = [];
        $sql    = "SELECT s.StudentID, s.StudentSurname, s.StudentPreferred, s.StudentYearLevel, s.StudentOccupEmail, s.StudentTutor
        FROM vstudents s
        LEFT JOIN FILESEMESTERS FS ON FS.FILEYEAR = S.FILEYEAR AND FS.FILESEMESTER = S.FILESEMESTER
        RIGHT JOIN (SELECT ID FROM pvStudentContactDetails
            WHERE (contacttype = 'SCT' OR contacttype = 'SC2')
            AND (linkedID = ? OR contactspouseID = ?)) cd ON cd.id = s.studentid
        WHERE fs.systemcurrentflag = 1";
        $params = [$id, $id];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $stuArr[] = [
                "ID"            => $row["StudentID"],
                "Surname"       => $row["StudentSurname"],
                "Preferred"     => $row["StudentPreferred"],
                "YearLevelCode" => $row["StudentYearLevel"],
                "Email"         => $row["StudentOccupEmail"],
                "TutorGroup"    => $row["StudentTutor"],
            ];
        }

        return $stuArr;
    }

    /**
     * Handle request for fetching a parent.
     *
     * @param array $data `$data['email']` must be a parent's email address.
     * @return array
     * @throws OutOfBoundsException if `$data['email']` is not an email address.
     */
    public function handleGetParentRequest(array $data): array
    {
        $email = $data['email'] ?? null;
        if (!isset($email)) {
            throw new OutOfBoundsException('Parent email not provided');
        }

        $parentArr = [];
        $sql       = "SELECT c.id, c.given1, c.surname FROM community c
        LEFT JOIN Constituencies con ON con.id = c.id
        WHERE con.ConstitCode LIKE '%@PC%' AND (c.email = ? OR c.occupemail = ?)";
        $params    = [$email, $email];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $parentArr[] = [
                "ID"      => $row["id"],
                "Surname" => $row["surname"],
                "Given"   => $row["given1"],
            ];
        }

        return $parentArr;
    }

    /**
     * Handle health check request.
     *
     * @return array
     */
    public function handleHealthCheckRequest(): array
    {
        $sql = "SELECT count(*) FROM luYearLevel";
        $this->externalDb->executeQuery($sql);
        return ['success' => true, 'timestamp' => time()];
    }
}
