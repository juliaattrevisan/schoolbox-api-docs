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
    private ViewSelector $viewSelector;
    /**
     * Constructor
     *
     * @param IConnector $externalDb
     * @param ViewSelector $viewSelector
     */
    public function __construct(private IConnector $externalDb, ViewSelector $viewSelector)
    {
        $this->viewSelector = $viewSelector;
    }

    /**
     * Handle action.
     *
     * @param array $data
     * @return array
     * @throws OutOfBoundsException if something was missing in `$data`
     */
    public function handle(array $data): array
    {
        $method = $data['m'] ?? null;
        if (!isset($method)) {
            throw new OutOfBoundsException('Method does not exist');
        }

        switch ($method) {
            case 'allYearLevels':
                return $this->handleAllYearLevelsRequest($data);
            case 'allClasses':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleAllClassesRequest($data, $selectedView);
            case 'getStudentsByClass':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleGetStudentsByClassRequest($data, $selectedView);
            case 'allStaffs':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleAllStaffsRequest($data, $selectedView);
            case 'allStudents':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleAllStudentsRequest($data, $selectedView);
            case 'getStudentsAvatar':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleGetStudentsAvatarRequest($data, $selectedView);
            case 'getStudentsMedical':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleGetStudentsMedicalRequest($data, $selectedView);
            case 'getStudentsFromParent':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleGetStudentsFromParentRequest($data, $selectedView);
            case 'allParents':
                $selectedView = $this->viewSelector->getView($method);
                return $this->handleAllParentsRequest($data, $selectedView);
            case 'allMedicalConditionTypes':
                return $this->handleAllMedicalConditionTypesRequest();
            case 'allMedicalConditionSeverityTypes':
                return $this->handleAllMedicalConditionSeverityTypesRequest();
            case 'allGenderTypes':
                return $this->handleAllGenderTypesRequest();
            case 'allStates':
                return $this->handleAllStatesRequest();
            case 'allOccupationPositionTypes':
                return $this->handleAllOccupationPositionTypesRequest();
            case 'allQualificationLevels':
                return $this->handleAllQualificationLevelsRequest();
            case 'allReligions':
                return $this->handleAllReligionsRequest();
            case 'allNationalities':
                return $this->handleAllNationalitiesRequest();
            case 'allOccupationTypes':
                return $this->handleAllOccupationTypesRequest();
            case 'allCountries':
                return $this->handleAllCountriesRequest();
            case 'allLanguages':
                return $this->handleAllLanguagesRequest();
            case 'allRelationshipTypes':
                return $this->handleAllRelationshipTypesRequest();
            case 'healthcheck':
                return $this->handleHealthCheckRequest();
            default:
                throw new OutOfBoundsException('Method does not exist');
        }
    }

    /**
     * Checking if the column exists in the table
     *
     * @param string $columnName
     * @param string $tableName
     * @return bool
     */
    private function columnExists(string $columnName, string $tableName): bool
    {
        $sql    = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?";
        $params = [
            $tableName,
            $columnName,
        ];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        return $stmt->fetchColumn() !== false;
//        return count($stmt) > 0;
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
     * @param string $selectedView paperly view
     *
     * @return array
     */
    public function handleAllClassesRequest(array $data, string $selectedView): array
    {
        $yearLevelArr = [];
        $params       = [];
        $sql          = "SELECT * FROM {$selectedView} WHERE 1=1";

        if (isset($data['code'])) {
            $codes        = is_array($data['code']) ? $data['code'] : explode(',', $data['code']);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql          .= " AND classcode IN ($placeholders)";
            $params       = array_merge($params, $codes);
        }

        $sql .= " ORDER BY ClassCode";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $yearLevelArr[] = [
                "Code"        => $row["ClassCode"],
                "Description" => $row["ClassDesc"],
            ];
        }

        return $yearLevelArr;
    }

    /**
     * Handle request for getting students from the class code
     *
     * @param array $data `$data['code']` may be an array or CSV list of codes:
     *                    if set, return only classes which match these codes.
     * @param string $selectedView paperly view
     *
     * @return array
     */
    public function handleGetStudentsByClassRequest(array $data, string $selectedView): array
    {
        $yearLevelArr = [];
        $params       = [];
        $sql          = "SELECT DISTINCT ClassCode, ID, ClassDesc, Surname, Preferred FROM {$selectedView} WHERE 1=1";

        if (isset($data['code'])) {
            $codes        = is_array($data['code']) ? $data['code'] : explode(',', $data['code']);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql          .= " AND classcode IN ($placeholders)";
            $params       = array_merge($params, $codes);
        }

        $sql .= " ORDER BY ClassCode";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $yearLevelArr[] = [
                "ID"          => $row["ID"],
                "ClassCode"   => $row["ClassCode"],
                "ClassDesc"   => $row["ClassDesc"],
                "Surname"     => $row["Surname"],
                "Preferred"   => $row["Preferred"],
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
     * @param string $selectedView paperly view
     *
     * @return array
     */
    public function handleAllStaffsRequest(array $data, string $selectedView): array
    {
        $stfArr = [];
        $params = [];

        $sql = "SELECT StaffID, StaffPreferred, StaffSurname, StaffEmail
                FROM (
                    SELECT StaffID, StaffPreferred, StaffSurname, StaffEmail, ClassCode, NormalYearLevel
                    FROM {$selectedView} WHERE 1=1
                ";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND StaffID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        if (isset($data['email'])) {
            $sql         .= " AND StaffEmail = ?";
            $params[]    = $data['email'];
        }

        if (isset($data['year_level'])) {
            $yls = is_array($data['year_level']) ? $data['year_level'] : explode(',', $data['year_level']);
            $placeholders = implode(',', array_fill(0, count($yls), '?'));
            $params = array_merge($params, $yls);

            $sql .= " AND NormalYearLevel IN ($placeholders)";
        }

        if (isset($data['class_code'])) {
            $classCodes = is_array($data['class_code']) ? $data['class_code'] : explode(',', $data['class_code']);
            $placeholders = implode(',', array_fill(0, count($classCodes), '?'));
            $params = array_merge($params, $classCodes);
            $sql .= " AND classcode IN ($placeholders)";
        }

        $sql .= " ) filtered_results
                GROUP BY StaffID, StaffPreferred, StaffSurname, StaffEmail
                ORDER BY StaffSurname";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $stfArr[] = [
                "ID"         => $row["StaffID"],
                "Preferred"  => $row["StaffPreferred"],
                "Surname"    => $row["StaffSurname"],
                "Email"      => $row["StaffEmail"],
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
     * @param string $selectedView paperly view
     *
     * @return array
     */
    public function handleAllStudentsRequest(array $data, string $selectedView): array
    {
        $stuArr = [];
        $params = [];
        $sql    = "SELECT * FROM {$selectedView} WHERE 1=1";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND StudentID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        if (isset($data['email'])) {
            $sql      .= " AND StudentEmail = ?";
            $params[] = $data['email'];
        }

        if (isset($data['year_level'])) {
            $yls          = is_array($data['year_level']) ? $data['year_level'] : explode(',', $data['year_level']);
            $placeholders = implode(',', array_fill(0, count($yls), '?'));
            $sql          .= " AND StudentYearLevel IN ($placeholders)";
            $params       = array_merge($params, $yls);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        $students = [];
        foreach ($stmt as $row) {
            $studentId = $row["StudentID"];
            $birthDate = $row['StudentBirthDate'];
            $formattedBirthDate = ($birthDate !== null)
                ? (\Alaress_Date_Format::newFromTimestamp(strtotime($birthDate)))->getUsingFormat('Y-m-d')
                : null;

            if (!isset($students[$studentId])) {
                $students[$studentId] = [
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
                    "BirthDate"            => $formattedBirthDate,
                    "Contact1Surname"      => null,
                    "Contact1Name"         => null,
                    "Contact1Mobile"       => null,
                    "Contact1Email"        => null,
                    "Contact2Surname"      => null,
                    "Contact2Name"         => null,
                    "Contact2Mobile"       => null,
                    "Contact2Email"        => null,
                ];
            }

            // Determine if we should add the contact as Contact1 or Contact2
            if (is_null($students[$studentId]["Contact1Surname"])) {
                $students[$studentId]["Contact1Surname"] = $row["ContactSurname"];
                $students[$studentId]["Contact1Name"] = $row["ContactPreferred"];
                $students[$studentId]["Contact1Mobile"] = $row["ContactMobilePhone"];
                $students[$studentId]["Contact1Email"] = $row["ContactDefaultEmail"];
            } else if (is_null($students[$studentId]["Contact2Surname"])) {
                $students[$studentId]["Contact2Surname"] = $row["ContactSurname"];
                $students[$studentId]["Contact2Name"] = $row["ContactPreferred"];
                $students[$studentId]["Contact2Mobile"] = $row["ContactMobilePhone"];
                $students[$studentId]["Contact2Email"] = $row["ContactDefaultEmail"];
            }
        }

        $stuArr = array_values($students);

        return $stuArr;
    }

    /**
     * Handle request for student avatars (profile pictures).
     *
     * @param array $data `$data['id']` may be an array or CSV list of IDs:
     *                    if set, return only avatars for these students.
     * @param string $selectedView paperly view
     *
     * @return array
     */
    public function handleGetStudentsAvatarRequest(array $data, string $selectedView): array
    {
        $avatarArr   = [];
        $params      = [];
        $columnToUse = 'PhotoMediumRes';

        if (!$this->columnExists($columnToUse, $selectedView)) {
            $columnToUse = 'Photo';
        }

        $sql = "SELECT DISTINCT ID, {$columnToUse} FROM {$selectedView} WHERE 1=1";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND ID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        //Since the ID is nullable, we have to check
        $sql .= " AND ID IS NOT NULL";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $photoColumn = $row[$columnToUse];
            $avatarArr[$row["ID"]] = [
                "ID"     => $row["ID"],
                "Avatar" => $photoColumn ? base64_encode($photoColumn) : '',
            ];
        }

        return $avatarArr;
    }

    /**
     * Handle request for student medical data.
     *
     * @param array $data `$data['id']` may be an array or CSV list of IDs:
     *                    if set, return only medical data for these students.
     * @param string $selectedView paperly view
     *
     * @return array
     */
    public function handleGetStudentsMedicalRequest(array $data, string $selectedView): array
    {
        $stuMedicalArr = [];
        $params        = [date("Y")];

        $sql = "SELECT * FROM {$selectedView} WHERE 1=1";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND ID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $stuMedicalArr[$row["ID"]][] = [
                "ID"                                  => $row["ID"],
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
     * @param string $selectedView paperly view
     * @return array
     * @throws OutOfBoundsException if `$data['id']` is not a parent ID.
     */
    public function handleGetStudentsFromParentRequest(array $data, string $selectedView): array
    {
        $id = $data['id'] ?? null;
        if (!isset($id)) {
            throw new OutOfBoundsException('Parent ID not provided');
        }

        $stuArr = [];

        //ContactSposeID column removed on Paperly's request, because no such column exist in the view
        $sql = "SELECT * FROM {$selectedView} WHERE (contactID = ?)";

        $params = [$id];

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
     * Handle request for fetching parents
     *
     * @param array  $data
     * @param string $selectedView
     * @return array
     */
    public function handleAllParentsRequest(array $data, string $selectedView): array
    {
        $parentArr = [];
        $email     = $data['email'] ?? null;
        $params    = [];
        $sql       = "SELECT * FROM {$selectedView} WHERE 1=1";

        if (isset($data['id'])) {
            $ids          = is_array($data['id']) ? $data['id'] : explode(',', $data['id']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          .= " AND ID IN ($placeholders)";
            $params       = array_merge($params, $ids);
        }

        if (isset($email)) {
            $sql    .= " AND (email = ? OR occupEmail = ?)";
            $params = array_merge($params, [$email, $email]);
        }

        $sql .= " ORDER BY Surname";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $parentArr[] = [
                "ID"      => $row["ID"],
                "Surname" => $row["Surname"],
                "Given"   => $row["Given1"],
                "Mobile"  => $row["MobilePhone"],
                "Email"   => $row["Email"]
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

    public function handleAllMedicalConditionTypesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luMedicalConditionType WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }

    /**
     * Handle getting medical condition serverity type
     *
     * @return array
     */
    public function handleAllMedicalConditionSeverityTypesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luMedicalConditionSeverity WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }

    /**
     * Handle getting gender types
     *
     * @return array
     */
    public function handleAllGenderTypesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luGender WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }

    /**
     * Handle getting all states
     *
     * @return array
     */
    public function handleAllStatesRequest(): array
    {
        $stateArray = [];
        $sql        = "SELECT Code, Description FROM luState";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $stateArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $stateArray;
    }

    /**
     * Handle getting occupation position types
     *
     * @return array
     */
    public function handleAllOccupationPositionTypesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luOccupationPosition WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }

    /**
     * Handle getting qualification levels
     *
     * @return array
     */
    public function handleAllQualificationLevelsRequest(): array
    {
        $levelArray = [];
        $sql       = "SELECT Code, Description FROM luQualificationLevel WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $levelArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $levelArray;
    }

    /**
     * Handle getting all religions
     *
     * @return array
     */
    public function handleAllReligionsRequest(): array
    {
        $religionArray = [];
        $sql           = "SELECT Code, Description FROM luReligion WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $religionArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $religionArray;
    }

    /**
     * Handle getting nationalities
     *
     * @return array
     */
    public function handleAllNationalitiesRequest(): array
    {
        $nationalityArray = [];
        $sql           = "SELECT Code, Description FROM luNationality";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $nationalityArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $nationalityArray;
    }

    /**
     * Handle getting occupation types
     *
     * @return array
     */
    public function handleAllOccupationTypesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luOccupation";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }

    /**
     * Handle getting all countries
     *
     * @return array
     */
    public function handleAllCountriesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luCountry WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }

    /**
     * Handle getting all languages
     *
     * @return array
     */
    public function handleAllLanguagesRequest(): array
    {
        $languageArray = [];
        $sql           = "SELECT Code, Description FROM luLanguage WHERE ActiveFlag = 1";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $languageArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $languageArray;
    }

    /**
     * Handle getting relationship types
     *
     * @return array
     */
    public function handleAllRelationshipTypesRequest(): array
    {
        $typeArray = [];
        $sql       = "SELECT Code, Description FROM luRelationship";

        $stmt = $this->externalDb->executeQuery($sql);
        foreach ($stmt as $row) {
            $typeArray[] = [
                "Code"        => $row["Code"],
                "Description" => $row["Description"],
            ];
        }

        return $typeArray;
    }
}
