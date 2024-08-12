<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the Paperly agent.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param string     $ipWhitelist A comma-separated whitelist of IP addresses which may access the agent.
     * @param string     $secret      A shared secret between the agent and this instance.
     * @param IConnector $externalDb  A connection to whatever external database the agent uses:
     *                                currently only Synergetic is supported.
     */
    public function __construct(
        private string $ipWhitelist,
        private string $secret,
        private IConnector $externalDb,
    ) {
    }

    /**
     * @api POST /paperly/agent
     * @since 24.0
     *
     * @return void
     */
    public function postAgentRequest(): void
    {
        // Check IP address
        if (!IpUtils::checkIp($this->getSession()->get('ip'), $this->ipWhitelist)) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
        }

        $data   = $this->request->getParsedBody();
        $secret = $data['v'] ?? null;
        if (!isset($secret) && $this->secret !== $secret) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
            return;
        }

        $method = $data['m'] ?? null;
        if (!isset($method)) {
            $this->sendJsonError('Method does not exist', Response::HTTP_NOT_FOUND);
        }

        switch ($method) {
            case 'allYearLevels':
                $this->handleAllYearLevelsRequest($data);
                break;
            case 'allClasses':
                $this->handleAllClassesRequest($data);
                break;
            case 'allStaffs':
                $this->handleAllStaffsRequest($data);
                break;
            case 'allStudents':
                $this->handleAllStudentsRequest($data);
                break;
            case 'getStudentsAvatar':
                $this->handleGetStudentsAvatarRequest($data);
                break;
            case 'getStudentsMedical':
                $this->handleGetStudentsMedicalRequest($data);
                break;
            case 'getStudentsFromParent':
                $this->handleGetStudentsFromParentRequest($data);
                break;
            case 'getParent':
                $this->handleGetParentRequest($data);
                break;
            case 'healthcheck':
                $this->handleHealthCheckRequest();
                break;
            default:
                $this->sendJsonError('Method does not exist', Response::HTTP_NOT_FOUND);
        }
    }

    private function handleAllYearLevelsRequest(array $data): void
    {
        $yearLevelArr = [];
        $params       = [];
        $sql          = "SELECT DISTINCT Code, Description FROM luYearLevel";

        if (isset($data['code'])) {
            $codes        = is_array($data['code']) ? $data['code'] : explode(',', $data['code']);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql          .= " AND Code IN ($placeholders)";
            $params       = array_merge($params, $codes);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $yearLevelArr[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        $this->response = $this->responseAsJson($yearLevelArr);
    }

    private function handleAllClassesRequest(array $data): void
    {
        $yearLevelArr = [];
        $params       = [];
        $sql          = "SELECT DISTINCT sc.classcode, sc.Description, scs.StaffID, CONCAT(c.Preferred, ' ', c.Surname) AS TeacherName, c.OccupEmail
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

        $this->response = $this->responseAsJson($yearLevelArr);
    }

    private function handleAllStaffsRequest(array $data): void
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
                "ID"            => $row["staffid"],
                "Preferred"     => $row["staffpreferredname"],
                "Surname"       => $row["staffsurname"],
                "Email"         => $row["staffoccupemail"],
                "ActiveFlag"    => $row["ActiveFlag"],
                'YearLevelCode' => $row["StaffYearLevel"],
            ];
        }


        $this->response = $this->responseAsJson($stfArr);
    }

    private function handleAllStudentsRequest(array $data): void
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
                s.StudentBirthDate, s.StudentYearLevel, s.StudentBoarder, s.StudentTutor, s.StudentHouse, scd.ContactID,
                c1.Title, c1.Surname, c1.Preferred, c1.MobilePhone, c1.email, c1.occupemail,
                scd.DefaultEmail, scd.ContactSpouseID,
                c2.Title AS SpouseTitle, c2.Surname AS SpouseSurname,
                c2.Preferred AS SpousePreferred, c2.MobilePhone AS SpouseMobile,
                c2.email AS SpouseDefaultEmail, c2.occupemail AS SpouseOccupEmail,
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
                "BirthDate"            => $row["StudentBirthDate"]->format("Y-m-d"),
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

        $this->response = $this->responseAsJson($stuArr);
    }

    private function handleGetStudentsAvatarRequest(array $data): void
    {
        $avatarArr = [];
        $params    = [];

        $sql = "SELECT DISTINCT ID, PhotoMediumRes FROM vphotos";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " WHERE ID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $avatarArr[$row["ID"]] = [
                "ID"        => $row["ID"],
                "Avatar"    => base64_encode($row["PhotoMediumRes"]),
                "Thumbnail" => base64_encode($row["PhotoThumnail"]),
            ];
        }

        $this->response = $this->responseAsJson($avatarArr);
    }

    private function handleGetStudentsMedicalRequest(array $data): void
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

        $this->response = $this->responseAsJson($stuMedicalArr);
    }

    private function handleGetStudentsFromParentRequest(array $data): void
    {
        $id = $data['id'] ?? null;
        if (!isset($id)) {
            $this->sendJsonError('Parent ID not provided', Response::HTTP_NOT_FOUND);
            return;
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

        $this->response = $this->responseAsJson($stuArr);
    }

    private function handleGetParentRequest(array $data): void
    {
        $email = $data['email'] ?? null;
        if (!isset($email)) {
            $this->sendJsonError('Parent email not provided', Response::HTTP_NOT_FOUND);
            return;
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

        $this->response = $this->responseAsJson($parentArr);
    }

    private function handleHealthCheckRequest(): void
    {
        $sql = "SELECT count(*) FROM luYearLevel";
        $this->externalDb->executeQuery($sql);
        $this->response = $this->responseAsJson(['success' => true, 'timestamp' => time()]);
    }
}
