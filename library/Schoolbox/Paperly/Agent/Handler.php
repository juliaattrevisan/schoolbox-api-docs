<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use OutOfBoundsException;
use InvalidArgumentException;
use Exception;

/**
 * Handler for the various actions the Paperly agent may perform.
 */
class Handler
{
    /**
     * Constructor
     *
     * @param IConnector   $externalDb
     * @param ViewSelector $viewSelector
     */
    public function __construct(
        private IConnector $externalDb,
        private ViewSelector $viewSelector
    ) {
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
                return $this->handleAllClassesRequest($data);
            case 'getStudentsByClass':
                return $this->handleGetStudentsByClassRequest($data);
            case 'allStaffs':
                return $this->handleAllStaffsRequest($data);
            case 'allStudents':
                return $this->handleAllStudentsRequest($data);
            case 'getStudentsAvatar':
                return $this->handleGetStudentsAvatarRequest($data);
            case 'getStudentsMedical':
                return $this->handleGetStudentsMedicalRequest($data);
            case 'getStudentsFromParent':
                return $this->handleGetStudentsFromParentRequest($data);
            case 'allParents':
                return $this->handleAllParentsRequest($data);
            case 'getDetailsData':
                return $this->handleGetDetailsDataRequest($data);
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
            case 'updateDetailsData':
                $this->validateUpdateDetailsRequestData($data);
                return $this->handleUpdateDetailsDataRequest($data);
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
     * @param array  $data         `$data['code']` may be an array or CSV list of codes:
     *                             if set, return only classes which match these codes.
     *
     * @return array
     */
    public function handleAllClassesRequest(array $data): array
    {
        $classArr = [];
        $params   = [];
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_CLASSES);
        $sql      = "SELECT * FROM {$selectedView} WHERE 1=1";

        if (isset($data['code'])) {
            $codes        = is_array($data['code']) ? $data['code'] : explode(',', $data['code']);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql          .= " AND classcode IN ($placeholders)";
            $params       = array_merge($params, $codes);
        }

        $sql .= " ORDER BY ClassCode";

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            if (!isset($classArr[$row["ClassCode"]])) {
                $classArr[$row["ClassCode"]] = [
                    "Code"        => $row["ClassCode"],
                    "Description" => $row["ClassDesc"],
                ];
            }
        }

        // Convert associative array to indexed array
        return array_values($classArr);
    }

    /**
     * Handle request for getting students from the class code
     *
     * @param array  $data         `$data['code']` may be an array or CSV list of codes:
     *                             if set, return only classes which match these codes.
     *
     * @return array
     */
    public function handleGetStudentsByClassRequest(array $data): array
    {
        $yearLevelArr = [];
        $params       = [];
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_CLASSES);
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
     * @param array  $data         `$data['id']` may be an array or CSV list of IDs:
     *                             if set, return only staff which match these IDs.
     *                             `$data['email']` may be an email address: if set,
     *                             return only this staff member.
     *                             `$data['year_level']` may be an array or CSV list of
     *                             year levels: if set, return only staff which teach
     *                             these year levels.
     *
     * @return array
     */
    public function handleAllStaffsRequest(array $data): array
    {
        $stfArr = [];
        $params = [];
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_STAFF);
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
     * @param array  $data         `$data['id']` may be an array or CSV list of IDs:
     *                             if set, return only students which match these IDs.
     *                             `$data['email']` may be an email address: if set,
     *                             return only this student.
     *                             `$data['year_level']` may be an array or CSV list of
     *                             year levels: if set, return only students within these
     *                             year levels.
     *                             `$data['class']` may be an array or CSV list of
     *                             classes: if set, return only students within these
     *                             classes.
     *
     * @return array
     */
    public function handleAllStudentsRequest(array $data): array
    {
        $params = [];
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_STUDENTS);
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

        return array_values($students);
    }

    /**
     * Handle request for student avatars (profile pictures).
     *
     * @param array  $data         `$data['id']` may be an array or CSV list of IDs:
     *                             if set, return only avatars for these students.
     *
     * @return array
     */
    public function handleGetStudentsAvatarRequest(array $data): array
    {
        $avatarArr   = [];
        $params      = [];
        $columnToUse = 'PhotoMediumRes';
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_AVATAR);
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
     * @param array  $data         `$data['id']` may be an array or CSV list of IDs:
     *                             if set, return only medical data for these students.
     *
     * @return array
     */
    public function handleGetStudentsMedicalRequest(array $data): array
    {
        $stuMedicalArr = [];
        $params        = [date("Y")];
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_MEDICAL);
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
     * @param array  $data         `$data['id']` must be a parent ID.
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
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_FROM_PARENT);
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
     * @return array
     */
    public function handleAllParentsRequest(array $data): array
    {
        $parentArr = [];
        $email     = $data['email'] ?? null;
        $params    = [];
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_PARENTS);
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
                "ID"        => $row["ID"],
                "Surname"   => $row["Surname"],
                "Preferred" => $row["Given1"],
                "Mobile"    => $row["MobilePhone"],
                "Email"     => $row["Email"],
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

    public function handleGetDetailsDataRequest(array $data): array
    {
        if (!isset($data['id']) || !$this->validateParent($data['id'])) {
            throw new InvalidArgumentException("Invalid Parent ID");
        }

        $parentId = $data['id'];

        $relatedData = $this->getRelatedData($parentId);

        $linkedID = $relatedData['linkedID'];
        $spouseID = $relatedData['spouseID'];

        $authArr = [];

        if ($parentId == $linkedID) {
            $authArr = array_merge(
                $authArr,
                $this->getPrimaryData($parentId, $relatedData['linkedIDRel'])
            );
        }

        if ($parentId == $spouseID) {
            $authArr = array_merge(
                $authArr,
                $this->getSpouseData($parentId, $relatedData['spouseRel'])
            );
        }

        $authArr = array_merge(
            $authArr,
            $this->getStudentData($relatedData['studentIDs'])
        );

        return $authArr;
    }

    private function validateParent(?string $parentId): bool
    {
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_PARENTS);

        $sql    = "SELECT * FROM {$selectedView} WHERE ID = ?";
        $params = [$parentId];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        return $stmt->fetchColumn() !== false;
    }

    private function getRelatedData(string $parentId): array
    {
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_STUDENTS);

        $relatedData = [
            'studentIDs'  => [],
            'linkedID'    => null,
            'linkedIDRel' => null,
            'spouseID'    => null,
            'spouseRel'   => null,
        ];

        $sql = "SELECT * FROM {$selectedView} WHERE ContactID = ?";

        $params = [$parentId];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $relatedData['studentIDs'][] = $row["StudentID"];
            $relatedData['linkedID']     = $row["ContactID"];
            $relatedData['linkedIDRel']  = $row["ContactRelationship"];
        }

        $relatedData['studentIDs'] = array_values(array_unique($relatedData['studentIDs']));

        return $relatedData;
    }

    private function getPrimaryData(string $parentId, string $parentRel): array
    {
        $primaryData = [];

        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_PARENTS);

        $sql = "SELECT * FROM {$selectedView} WHERE 1=1 AND ID = ?";

        $params = [$parentId];

        $stmt = $this->externalDb->executeQuery($sql, $params);

        foreach ($stmt as $row) {
            $birthDate          = $row['BirthDate'];
            $formattedBirthDate = ($birthDate !== null)
                ? (\Alaress_Date_Format::newFromTimestamp(strtotime($birthDate)))->getUsingFormat('d-m-Y')
                : null;

            $primaryData[] = [
                "Type"                          => "Primary",
                "ID"                            => $row["ID"],
                "Relationship"                  => $parentRel,
                "Gender"                        => $row["Gender"],
                "Surname"                       => $row["Surname"],
                "Preferred"                     => $row["Preferred"],
                "Given1"                        => $row["Given1"],
                "BirthDate"                     => $formattedBirthDate,
                "AddressID"                     => $row["AddressID"],
                "Address"                       => $row["HomeAddress1"],
                "Address2"                      => $row["HomeAddress2"],
                "Address3"                      => $row["HomeAddress3"],
                "Suburb"                        => $row["HomeSuburb"],
                "State"                         => $row["HomeState"],
                "Postcode"                      => $row["HomePostCode"],
                "SamePostal"                    => $row["HomeAddressSameFlag"],
                "PostalAddress"                 => $row["Address1"],
                "PostalAddress2"                => $row["Address2"],
                "PostalAddress3"                => $row["Address3"],
                "PostalSuburb"                  => $row["Suburb"],
                "PostalState"                   => $row["State"],
                "PostalPostcode"                => $row["PostCode"],
                "HomePhone"                     => $row["Phone"],
                "Mobile"                        => $row["MobilePhone"],
                "DefaultMobilePhoneCode"        => $row["DefaultMobilePhoneCode"],
                "Email"                         => $row["Email"],
                "OccupDesc"                     => $row["OccupDesc"],
                "OccupationPositionDescription" => $row["OccupationPositionDescription"],
                "OccupCompany"                  => $row["OccupCompany"],
                "OccupCode"                     => $row["OccupCode"],
                "OccupCountry"                  => $row["OccupCountryCode"],
                "OccupIndustryCode"             => $row["OccupIndustryCode"],
                "OccupPhone"                    => $row["OccupPhone"],
                "OccupEmail"                    => $row["OccupEmail"],
                "OccupAddress"                  => $row["OccupAddress1"],
                "OccupAddress2"                 => $row["OccupAddress2"],
                "OccupAddress3"                 => $row["OccupAddress3"],
                "OccupSuburb"                   => $row["OccupSuburb"],
                "OccupState"                    => $row["OccupState"],
                "OccupPostcode"                 => $row["OccupPostCode"],
                "ReligionDescription"           => $row["ReligionDescription"],
                "NationalityDescription"        => $row["NationalityDescription"],
                "CountryOfBirthDescription"     => $row["CountryOfBirthDescription"],
                "HomeLanguageDescription"       => $row["HomeLanguageDescription"],
                "HighestSecondaryYearLevel"     => $row["HighestSecondaryYearLevel"],
                "QualificationLevelDescription" => $row["QualificationLevelDescription"],
                "ReceiveMailFlag"               => $row["MailFlag"],
                "DefaultEmailCode"              => $row["DefaultEmailCode"],
            ];
        }
        return $primaryData;
    }

    private function getSpouseData(string $spouseID, $spouseRel): array
    {
        $spouseData = [];

        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_PARENTS);

        $sql    = "SELECT * FROM {$selectedView} WHERE 1=1 AND ID = ?";
        $params = [$spouseID];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $birthDate          = $row['BirthDate'];
            $formattedBirthDate = ($birthDate !== null)
                ? (\Alaress_Date_Format::newFromTimestamp(strtotime($birthDate)))->getUsingFormat('d-m-Y')
                : null;

            $spouseData[] = [
                "Type"                          => "Spouse",
                "ID"                            => $row["ID"],
                "Relationship"                  => $spouseRel,
                "Gender"                        => $row["Gender"],
                "Surname"                       => $row["Surname"],
                "Preferred"                     => $row["Preferred"],
                "Given1"                        => $row["Given1"],
                "BirthDate"                     => $formattedBirthDate,
                "AddressID"                     => $row["AddressID"],
                "Address"                       => $row["HomeAddress1"],
                "Address2"                      => $row["HomeAddress2"],
                "Address3"                      => $row["HomeAddress3"],
                "Suburb"                        => $row["HomeSuburb"],
                "State"                         => $row["HomeState"],
                "Postcode"                      => $row["HomePostCode"],
                "SamePostal"                    => $row["HomeAddressSameFlag"],
                "PostalAddress"                 => $row["Address1"],
                "PostalAddress2"                => $row["Address2"],
                "PostalAddress3"                => $row["Address3"],
                "PostalSuburb"                  => $row["Suburb"],
                "PostalState"                   => $row["State"],
                "PostalPostcode"                => $row["PostCode"],
                "HomePhone"                     => $row["Phone"],
                "Mobile"                        => $row["MobilePhone"],
                "DefaultMobilePhoneCode"        => $row["DefaultMobilePhoneCode"],
                "Email"                         => $row["Email"],
                "OccupDesc"                     => $row["OccupDesc"],
                "OccupationPositionDescription" => $row["OccupationPositionDescription"],
                "OccupCompany"                  => $row["OccupCompany"],
                "OccupCode"                     => $row["OccupCode"],
                "OccupCountry"                  => $row["OccupCountryCode"],
                "OccupIndustryCode"             => $row["OccupIndustryCode"],
                "OccupPhone"                    => $row["OccupPhone"],
                "OccupEmail"                    => $row["OccupEmail"],
                "OccupAddress"                  => $row["OccupAddress1"],
                "OccupAddress2"                 => $row["OccupAddress2"],
                "OccupAddress3"                 => $row["OccupAddress3"],
                "OccupSuburb"                   => $row["OccupSuburb"],
                "OccupState"                    => $row["OccupState"],
                "OccupPostcode"                 => $row["OccupPostCode"],
                "ReligionDescription"           => $row["ReligionDescription"],
                "NationalityDescription"        => $row["NationalityDescription"],
                "CountryOfBirthDescription"     => $row["CountryOfBirthDescription"],
                "HomeLanguageDescription"       => $row["HomeLanguageDescription"],
                "HighestSecondaryYearLevel"     => $row["HighestSecondaryYearLevel"],
                "QualificationLevelDescription" => $row["QualificationLevelDescription"],
                "ReceiveMailFlag"               => $row["MailFlag"],
                "DefaultEmailCode"              => $row["DefaultEmailCode"],
            ];
        }
        return $spouseData;
    }

    private function getStudentData(array $studentIDs): array
    {
        $authArr = [];

        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_STUDENTS);

        foreach ($studentIDs as $stuID) {
            $smcArr       = $this->getStudentMedicalConditions($stuID);
            $abiArr       = $this->getStudentAbilities($stuID);
            $consentArr   = $this->getStudentConsents($stuID);
            $livesWithArr = $this->getStudentLivesWith($stuID);

            $sql = "
                SELECT
                    s.StudentID,
                    s.StudentLegalFullName,
                    s.StudentBirthDate,
                    s.IndigenousFlag,
                    s.StudentTSIFlag,
                    s.StudentGender,
                    s.StudentPreferred,
                    s.StudentYearLevelDescription,
                    s.StudentTutor,
                    s.StudentHomeLanguageCode,
                    s.StudentDoctorName,
                    s.StudentDoctorPhone,
                    s.StudentDoctorAddress,
                    s.StudentDentistName,
                    s.StudentDentistPhone,
                    s.StudentDentistAddress,
                    s.StudentEmergencyName,
                    s.StudentEmergencyAddress,
                    s.StudentEmergencyPhone,
                    s.StudentEmergencyPhoneAlt,
                    s.StudentEmergencyRelat,
                    s.StudentEmergency2Name,
                    s.StudentEmergency2Address,
                    s.StudentEmergency2Phone,
                    s.StudentEmergency2PhoneAlt,
                    s.StudentEmergency2Relat,
                    s.StudentEmergency3Name,
                    s.StudentEmergency3Address,
                    s.StudentEmergency3Phone,
                    s.StudentEmergency3PhoneAlt,
                    s.StudentEmergency3Relat,
                    s.LegalPhotoPublicationFlag,
                    s.LegalPhotoPromFlag,
                    s.LegalPhotoWebFlag,
                    s.LegalParentsSeparatedFlag,
                    s.LegalCourtOrderType,
                    s.StudentMedicalAlertFlag
                FROM {$selectedView} s
                WHERE s.StudentID IN (?)
                ";

            $params = [$stuID];
            $stmt   = $this->externalDb->executeQuery($sql, $params);

            foreach ($stmt as $sturow) {
                if ($sturow["LegalParentsSeparatedFlag"] == 1 || $sturow["LegalCourtOrderType"] !== '') {
                    $authArr[] = ["Type" => "BLOCK"];
                    continue;
                }

                $birthDate          = $sturow['StudentBirthDate'];
                $formattedBirthDate = ($birthDate !== null)
                    ? (\Alaress_Date_Format::newFromTimestamp(strtotime($birthDate)))->getUsingFormat('d-m-Y')
                    : null;

                $authArr[] = [
                    "Type"                        => "Student",
                    "ID"                          => $sturow["StudentID"],
                    "StudentLegalFullName"        => $sturow["StudentLegalFullName"],
                    "StudentPreferred"            => $sturow["StudentPreferred"],
                    "StudentGender"               => $sturow["StudentGender"],
                    "IndigenousFlag"              => $sturow["IndigenousFlag"],
                    "StudentTSIFlag"              => $sturow["StudentTSIFlag"],
                    "StudentHomeLanguageCode"     => $sturow["StudentHomeLanguageCode"],
                    "StudentBirthDate"            => $formattedBirthDate,
                    "StudentYearLevelDescription" => $sturow["StudentYearLevelDescription"],
                    "StudentTutor"                => $sturow["StudentTutor"],
                    "StudentDoctorName"           => $sturow["StudentDoctorName"],
                    "StudentDoctorPhone"          => $sturow["StudentDoctorPhone"],
                    "StudentDoctorAddress"        => trim(
                        preg_replace(
                            '/\s+/',
                            ' ',
                            str_replace(
                                '"',
                                '\"',
                                str_replace(
                                    "'",
                                    "\'",
                                    $sturow["StudentDoctorAddress"]
                                )
                            )
                        )
                    ),
                    "StudentDentistName"          => $sturow["StudentDentistName"],
                    "StudentDentistPhone"         => $sturow["StudentDentistPhone"],
                    "StudentDentistAddress"       => trim(
                        preg_replace(
                            '/\s+/',
                            ' ',
                            str_replace(
                                '"',
                                '\"',
                                str_replace(
                                    "'",
                                    "\'",
                                    $sturow["StudentDentistAddress"]
                                )
                            )
                        )
                    ),
                    "StudentEmergencyName"        => $sturow["StudentEmergencyName"],
                    "StudentEmergencyAddress"     => trim(
                        preg_replace(
                            '/\s\s+/',
                            ' ',
                            $sturow["StudentEmergencyAddress"]
                        )
                    ),
                    "StudentEmergencyPhone"       => $sturow["StudentEmergencyPhone"],
                    "StudentEmergencyPhoneAlt"    => $sturow["StudentEmergencyPhoneAlt"],
                    "StudentEmergencyRelatDesc"   => $sturow["StudentEmergencyRelat"],
                    "StudentEmergency2Name"       => $sturow["StudentEmergency2Name"],
                    "StudentEmergency2Address"    => trim(
                        preg_replace(
                            '/\s\s+/',
                            ' ',
                            $sturow["StudentEmergency2Address"]
                        )
                    ),
                    "StudentEmergency2Phone"      => $sturow["StudentEmergency2Phone"],
                    "StudentEmergency2PhoneAlt"   => $sturow["StudentEmergency2PhoneAlt"],
                    "StudentEmergency2RelatDesc"  => $sturow["StudentEmergency2Relat"],
                    "StudentEmergency3Name"       => $sturow["StudentEmergency3Name"],
                    "StudentEmergency3Address"    => trim(
                        preg_replace(
                            '/\s\s+/',
                            ' ',
                            $sturow["StudentEmergency3Address"]
                        )
                    ),
                    "StudentEmergency3Phone"      => $sturow["StudentEmergency3Phone"],
                    "StudentEmergency3PhoneAlt"   => $sturow["StudentEmergency3PhoneAlt"],
                    "StudentEmergency3RelatDesc"  => $sturow["StudentEmergency3Relat"],
                    "DoNotUsePhoto"               => $sturow["LegalPhotoPublicationFlag"],
                    "DoNotPublishPhoto"           => $sturow["LegalPhotoPromFlag"],
                    "PhotoWebFlag"                => $sturow["LegalPhotoWebFlag"],
                    "StudentMedicalAlertFlag"     => $sturow["StudentMedicalAlertFlag"],
                    "StudentMedicalConditions"    => $smcArr,
                    "StudentAbilities"            => $abiArr,
                    "StudentConsents"             => $consentArr,
                    "StudentLivesWith"            => $livesWithArr,
                ];
            }
        }
        return $authArr;
    }

    private function getStudentMedicalConditions(int $stuID): array
    {
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_MEDICAL);

        $smcArr = [];

        $sql    = "SELECT * FROM {$selectedView} WHERE ID = ?";
        $params = [$stuID];
        $stmt   = $this->externalDb->executeQuery($sql, $params);

        foreach ($stmt as $row) {
            $smcArr[] = [
                "MedicalConditionSeq"                 => $row["MedicalConditionSeq"],
                "MedicalConditionCode"                => $row["MedicalConditionCode"],
                "MedicalConditionTypeDescription"     => $row["MedicalConditionTypeDescription"],
                "MedicalConditionSeverityCode"        => $row["SeverityCode"],
                "MedicalConditionSeverityDescription" => $row["SeverityDescription"],
                "MedicalConditionDetails"             => htmlentities($row["ConditionDetails"]),
                "EmergencyTreatmentDetails"           => $row["EmergencyTreatmentDetails"],
                "OtherTreatmentDetails"               => $row["OtherTreatmentDetails"],
            ];
        }
        return $smcArr;
    }

    private function getStudentAbilities(int $stuID): array
    {
        $abiArr = [];

        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_ABILITIES);

        $sql    = "SELECT * FROM {$selectedView} WHERE ID = ?";
        $params = [$stuID];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $abiArr[] = [
                "AbilitySeq"              => $row["AbilitySeq"],
                "AbilityTypeCode"         => $row["AbilityTypeCode"],
                "AbilityTypeDesc"         => $row["TypeDesc"],
                "AbilityLevel"            => $row["AbilityLevel"],
                "AbilityLevelDescription" => $row["Description"],
                "AbilityComment"          => $row["AbilityComment"],
            ];
        }

        return $abiArr;
    }

    private function getStudentConsents(int $stuID): array
    {
        $consentArr = [];

        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_CONSENT);

        $sql = "
            SELECT
                smcons.ConsentSeq,
                smcons.ID,
                lu.Code,
                lu.Description,
                CASE
                    WHEN smcons.ConsentCode IS NOT NULL THEN 1
                    ELSE 0
                END AS GivenConsent
            FROM
                luConsentType AS lu
            LEFT JOIN (
                SELECT
                    DISTINCT ConsentCode, ID, ConsentSeq
                    FROM {$selectedView}
                    WHERE ID = ?
            ) AS smcons
            ON
                lu.Code = smcons.ConsentCode
            WHERE
                lu.ActiveFlag = 1
        ";

        $params = [$stuID];
        $stmt   = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            $consentArr[] = [
                "ConsentSeq"   => $row["ConsentSeq"],
                "ConsentCode"  => $row["Code"],
                "Consent"      => $row["Description"],
                "GivenConsent" => $row["GivenConsent"],
            ];
        }
        return $consentArr;
    }

    private function getStudentLivesWith(int $stuID): array
    {
        $livesWithArr = [];

        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_ALL_STUDENTS);

        $params = [$stuID];
        $sql    = "SELECT * FROM $selectedView WHERE StudentID = ?";
        $stmt   = $this->externalDb->executeQuery($sql, $params);
        foreach ($stmt as $row) {
            // Initialize the student array if it doesn't exist
            if (!isset($livesWithArr[$stuID])) {
                $livesWithArr[$stuID] = [
                    "Contact1" => null,
                    "Contact2" => null,
                ];
            }

            // Determine if we should add the contact as Contact1 or Contact2
            if (empty($livesWithArr[$stuID]["Contact1"])) {
                $livesWithArr[$stuID]["Contact1"] = $row["ContactID"];
            } elseif (empty($livesWithArr[$stuID]["Contact2"])) {
                $livesWithArr[$stuID]["Contact2"] = $row["ContactID"];
            }
        }
        return array_values($livesWithArr);
    }

    public function validateUpdateDetailsRequestData(array $data): void
    {
        if (!isset($data['data']) || count((array)$data['data']) < 1) {
            throw new OutOfBoundsException('Request body is not provided');
        }

        if (!isset($data['modified_by']) || $data['modified_by'] == '') {
            throw new OutOfBoundsException('Modified By is not provided');
        }

        if (!$this->validateModifiedByUser($data['modified_by'])) {
            throw new OutOfBoundsException('Invalid Modified By value');
        }
        // Validate each of the request params
        $this->validateUpdateDetailsRequestBody($data);
    }

    private function validateModifiedByUser(string $id)
    {
        $sql = "SELECT ID FROM Community WHERE ID = ?";
        $params = [$id];

        $stmt = $this->externalDb->executeQuery($sql, $params);
        return $stmt->fetchColumn() !== false;
    }

    private function validateUpdateDetailsRequestBody(array $results): void
    {
        $primaryAndSpouseValidators = $this->getParentUpdatedDetailsDataValidators();
        $studentValidators = $this->getStudentUpdatedDetailsDataValidators();

        $errors = [];

        foreach (['primary', 'spouse', 'student'] as $type) {
            if (isset($results['data'][$type])) {
                $data = $results['data'][$type];
                $validators = ($type === 'student') ? $studentValidators : $primaryAndSpouseValidators;

                if ($type === 'student') {
                    foreach ($data as $studentID => $studentData) {
                        foreach ($studentData as $key => $value) {
                            if (is_array($value)) {
                                foreach ($value as $arrayField) {
                                    foreach ($arrayField as $fieldKey => $fieldValue) {
                                        if (isset($validators[$fieldKey])) {
                                            if (!$validators[$fieldKey]($fieldValue)) {
                                                $errors[] =
                                                    "Invalid value for $fieldKey in $type user $studentID $key attribute";
                                            }
                                        } else {
                                            $errors[] = "Invalid key $fieldKey in $type user $studentID $key attribute";
                                        }
                                    }
                                }
                            } else {
                                if (isset($validators[$key])) {
                                    if (!$validators[$key]($value)) {
                                        $errors[] = "Invalid value for $key in $type user $studentID";
                                    }
                                } else {
                                    $errors[] = "Invalid key $key in $type user $studentID";
                                }
                            }
                        }
                    }
                } else {
                    foreach ($data as $key => $value) {
                        if (isset($validators[$key])) {
                            if (!$validators[$key]($value)) {
                                $errors[] = "Invalid value for $key in $type user";
                            }
                        } else {
                            $errors[] = "Invalid key $key in $type user";
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new OutOfBoundsException(implode("; ", $errors));
        }
    }

    private function getParentUpdatedDetailsDataValidators(): array
    {
        return [
            'ID' => function ($value) {
                return !empty($value) && $value !== 'null';
            },
            'Surname' => function ($value) {
                return is_string($value) && strlen($value) <= 30;
            },
            'Preferred' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 20;
            },
            'Given1' => function ($value) {
                return is_string($value) && strlen($value) <= 20;
            },
            'BirthDate' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                $date = (\Alaress_Date_Format::newFromTimestamp(strtotime($value)))->getUsingFormat('Y-m-d');
                return $date === $value;
            },
            'HomeAddress1' => function ($value) {
                return is_string($value);
            },
            'HomeAddress2' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'HomeSuburb' => function ($value) {
                return is_string($value);
            },
            'HomeState' => function ($value) {
                return is_string($value);
            },
            'HomePostCode' => function ($value) {
                return is_numeric($value);
            },
            'HomeAddressComma' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'HomeAddressFull' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'HomeAddressDPID' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value);
            },
            'HomeAddressLatitude' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && $value >= -90 && $value <= 90;
            },
            'HomeAddressLongitude' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && $value >= -180 && $value <= 180;
            },
            'Phone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value);
            },
            'MobilePhone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'Email' => function ($value) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 100;
            },
            'OccupDesc' => function ($value) {
                return is_string($value) && strlen($value) <= 50;
            },
            'OccupCode' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'OccupPositionCode' => function ($value) {
                return is_numeric($value) && strlen($value) <= 50;
            },
            'OccupCompany' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 50;
            },
            'OccupPhone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'OccupEmail' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 100;
            },
            'OccupAddress1' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 40;
            },
            'OccupAddress2' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 40;
            },
            'OccupAddress3' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 40;
            },
            'OccupSuburb' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 40;
            },
            'OccupState' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 5;
            },
            'OccupPostcode' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value);
            },
            'ReligionCode' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'NationalityCode' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'CountryOfBirthCode' => function ($value) {
                return is_string($value);
            },
            'HomeLanguageCode' => function ($value) {
                return is_string($value) || is_numeric($value);
            },
            'HighestSecondaryYearLevel' => function ($value) {
                return is_numeric($value);
            },
            'HighestQualificationLevel' => function ($value) {
                return is_numeric($value);
            },
        ];
    }

    private function getStudentUpdatedDetailsDataValidators(): array
    {
        return [
            'ID' => function ($value) {
                return !empty($value) && $value !== 'null';
            },
            'Preferred' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 20;
            },
            'Gender' => function ($value) {
                return is_string($value);
            },
            'IndigenousFlag' => function ($value) {
                return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
            },
            'TSIFlag' => function ($value) {
                return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
            },
            'HomeLanguageCode' => function ($value) {
                return is_string($value) || is_numeric($value);
            },
            'PhotoPromFlag' => function ($value) {
                return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
            },
            'PhotoPublicationFlag' => function ($value) {
                return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
            },
            'PhotoWebFlag' => function ($value) {
                return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
            },
            'DoctorName' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 80;
            },
            'DoctorPhone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'DoctorAddress' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 200;
            },
            'DentistName' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 80;
            },
            'DentistPhone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'DentistAddress' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 200;
            },
            'EmergencyName' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 80;
            },
            'EmergencyAddress' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 200;
            },
            'EmergencyPhone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'EmergencyPhoneAlt' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'EmergencyRelat' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'Emergency2Name' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 80;
            },
            'Emergency2Address' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 200;
            },
            'Emergency2Phone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'Emergency2PhoneAlt' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'Emergency2Relat' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'Emergency3Name' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 80;
            },
            'Emergency3Address' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value) && strlen($value) <= 200;
            },
            'Emergency3Phone' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'Emergency3PhoneAlt' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value) && strlen($value) <= 15;
            },
            'Emergency3Relat' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'MedicalConditionSeq'  => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value);
            },
            'MedicalConditionCode' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'SeverityCode'         => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'ConsentSeq' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_numeric($value);
            },
            'ConsentCode' => function ($value) {
                if (empty($value) || $value == 'null') {
                    return true;
                }
                return is_string($value);
            },
            'ConsentGivenBy' => function ($value) {
                return !empty($value) && $value !== 'null';
            },
        ];
    }

    public function handleUpdateDetailsDataRequest($data): array
    {
        try {
            $this->externalDb->beginTransaction();
            if (isset($data['data']['primary'])) {
                $this->handleUpdateParentDataRequest($data['data']['primary']);
            } else if (isset($data['data']['spouse'])) {
                $this->handleUpdateParentDataRequest($data['data']['spouse']);
            } else if (isset($data['data']['student'])) {
                $this->handleUpdateStudentDataRequest(
                    $data['data']['student'],
                    $data['modified_by']
                );
            }

            $this->externalDb->commit();
            return [
                'success' => true,
                'message' => 'Data has been updated successfully',
            ];
        } catch (Exception $e) {
            $this->externalDb->rollback();
            throw new OutOfBoundsException($e->getMessage(), $e->getCode());
        }
    }

    private function handleUpdateParentDataRequest($data): void
    {
        //Making sure that it is an array
        $data = (array) $data;
        $parentID = $data['ID'];

        // Remove ID from the $data array
        unset($data['ID']);

        // Separate AddressData from the rest of the data
        $addressData = [];
        $addressKeys = [
            'HomeAddress1',
            'HomeAddress2',
            'HomeSuburb',
            'HomeState',
            'HomePostCode',
            'HomeAddressComma',
            'HomeAddressFull',
            'HomeAddressDPID',
            'HomeAddressLatitude',
            'HomeAddressLongitude',
        ];
        foreach ($addressKeys as $key) {
            if (isset($data[$key])) {
                $addressData[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        $params = [];
        // Process AddressData if it exists
        if (!empty($addressData)) {
            $updateAddressData = "";

            foreach ($addressData as $column => $value) {
                $value = htmlspecialchars($value, ENT_QUOTES);
                $updateAddressData .= "$column = ?, ";
                $params[] = $value;
            }

            $sql = "UPDATE a SET " . rtrim($updateAddressData, ", ") . "
            FROM Addresses a
            LEFT JOIN Community c ON c.AddressID = a.AddressID
            WHERE c.ID = ?;";
            $params[] = $parentID;

            $stmt = $this->externalDb->prepareQuery($sql, $params)->execute();
            if ($stmt === false) {
                throw new Exception(
                    print_r($this->externalDb->errorInfo(), true)
                );
            }
        }

        // Process the rest of the data
        $communitySql = "";
        $params = [];
        foreach ($data as $key => $value) {
            $value = htmlspecialchars($value, ENT_QUOTES);

            if (empty($value) || $value == 'null') {
                $communitySql .= "$key = NULL, ";
            } else {
                if ($key == "BirthDate") {
                    $value .= " 00:00:00.000";
                }
                $communitySql .= "$key = ?, ";
                $params[] = $value;
            }
        }

        // Update community table
        if (!empty($communitySql)) {
            $primaryCommunitySQL = "UPDATE Community SET " . rtrim($communitySql, ", ") . " WHERE ID = ?;";
            $params[] = $parentID;

            $stmt = $this->externalDb->prepareQuery($primaryCommunitySQL, $params)->execute();
            if ($stmt === false) {
                throw new Exception(
                    print_r($this->externalDb->errorInfo(), true)
                );
            }
        }
    }

    private function handleUpdateStudentDataRequest(object $data, $modifiedBy): void
    {
        $groupedData = $this->groupStudentDataByDatabaseTable($data);
        $this->processGroupedStudentData($groupedData, $modifiedBy);
    }

    private function groupStudentDataByDatabaseTable(object $data): array
    {
        $groupedData = [
            'community' => [],
            'students' => [],
            'medical_conditions' => [
                'delete' => [],
                'create' => []

            ],
            'consents' => [
                'delete' => [],
                'create' => []
            ]
        ];

        foreach (array_keys(get_object_vars($data)) as $getStudentID) {
            $studentData = $data->$getStudentID;

            foreach ($studentData as $key => $value) {
                if (
                    !in_array(
                        $key,
                        [
                            'DeletedMedicalConditions',
                            'MedicalConditions',
                            'DeletedStudentConsents',
                            'StudentConsents',
                        ]
                    )
                ) {
                    $value = htmlspecialchars($value, ENT_QUOTES);

                    if (empty($value) || $value == 'null') {
                        $value = 'NULL';
                    }
                }

                // Group data for community table
                if (
                    in_array(
                        $key,
                        [
                            'Preferred',
                            'Gender',
                            'HomeLanguageCode',
                        ]
                    )
                ) {
                    $groupedData['community'][$getStudentID][$key] = $value;
                }

                // Group data for students table
                if ($key == 'IndigenousFlag' || $key == 'TSIFlag' || str_contains($key, 'Doctor') || str_contains($key, 'Dentist') || str_contains($key, 'Emergency')) {
                    $groupedData['students'][$getStudentID][$key] = $value;
                }

                // Group data for community legal table
                if (
                    in_array(
                        $key,
                        [
                            'PhotoPromFlag',
                            'PhotoPublicationFlag',
                            'PhotoWebFlag',
                        ]
                    )
                ) {
                    $groupedData['community_legal'][$getStudentID][$key] = $value;
                }

                // Group data for deleted student consents
                if ($key == 'DeletedStudentConsents') {
                    foreach ($value as $consent) {
                        $data = [];
                        foreach ($consent as $key => $fieldValue) {
                            $fieldValue = htmlspecialchars($fieldValue, ENT_QUOTES);
                            $data[$key] = $fieldValue;
                        }
                        $groupedData['consents']['delete'][$getStudentID][] = $data;
                    }
                }

                // Group data for student consents
                if ($key == 'StudentConsents') {
                    foreach ($value as $consent) {
                        $data = [];
                        foreach ($consent as $key => $fieldValue) {
                            $fieldValue = htmlspecialchars($fieldValue, ENT_QUOTES);
                            $data[$key] = $fieldValue;
                        }
                        $groupedData['consents']['create'][$getStudentID][] = $data;
                    }
                }

                // Group data for deleted student medical conditions
                if ($key == 'DeletedMedicalConditions') {
                    foreach ($value as $condition) {
                        foreach ($condition as $apiSeqId) {
                            $apiSeqId = htmlspecialchars($apiSeqId, ENT_QUOTES);
                            $groupedData['medical_conditions']['delete'][$getStudentID][] = $apiSeqId;
                        }
                    }
                }

                // Group data for student medical conditions
                if ($key == 'MedicalConditions') {
                    foreach ($value as $condition) {
                        $data = [];
                        foreach ($condition as $key => $fieldValue) {
                            $fieldValue = htmlspecialchars($fieldValue, ENT_QUOTES);
                            if (empty($fieldValue) || $fieldValue == 'null') {
                                $fieldValue = 'NULL';
                            }
                            $data[$key] = $fieldValue;
                        }
                        $groupedData['medical_conditions']['create'][$getStudentID][] = $data;
                    }
                }
            }

            // Check if MedicalAlertFlag needs to be updated
            $hasDeletedMedicalConditions = !empty($groupedData['medical_conditions']['delete'][$getStudentID]);
            $hasNewMedicalConditions = !empty($groupedData['medical_conditions']['create'][$getStudentID]);

            if ($hasDeletedMedicalConditions || $hasNewMedicalConditions) {
                // Fetch existing medical conditions for the student
                $existingMedicalConditions = $this->getStudentMedicalConditions($getStudentID);

                // Calculate the number of remaining conditions after deletion
                $remainingConditions = count($existingMedicalConditions) - count($groupedData['medical_conditions']['delete'][$getStudentID]);

                // Set MedicalAlertFlag based on the conditions
                if ($remainingConditions <= 0 && !$hasNewMedicalConditions) {
                    $groupedData['students'][$getStudentID]['MedicalAlertFlag'] = 0;
                } elseif ($remainingConditions == 0 && $hasNewMedicalConditions) {
                    $groupedData['students'][$getStudentID]['MedicalAlertFlag'] = 1;
                }
            }
        }
        return $groupedData;
    }

    private function processGroupedStudentData(array $groupedData, $modifiedBy)
    {
        // Process community data
        foreach ($groupedData['community'] as $studentID => $data) {
            $this->updateCommunityTable($data, $studentID);
        }

        // Process students data
        foreach ($groupedData['students'] as $studentID => $data) {
            $this->updateStudentsTable($data, $studentID);
        }

        // Process community legal data
        foreach ($groupedData['community_legal'] as $studentID => $data) {
            $this->updateCommunityLegalTable($data, $studentID, $modifiedBy);
        }

        // Process student medical conditions
        foreach ($groupedData['medical_conditions']['delete'] as $studentID => $conditions) {
            $this->deleteStudentMedicalConditions($conditions, $studentID);
        }
        foreach ($groupedData['medical_conditions']['create'] as $studentID => $conditions) {
            $this->createStudentMedicalConditions($conditions, $studentID, $modifiedBy);
        }

        // Process student consents
        foreach ($groupedData['consents']['delete'] as $consents) {
            $this->deleteStudentConsents($consents, $modifiedBy);
        }
        foreach ($groupedData['consents']['create'] as $studentID => $consents) {
            $this->createOrUpdateStudentConsents($consents, $studentID, $modifiedBy);
        }
    }

    private function updateCommunityTable($data, $studentID): void
    {
        if (!empty($data)) {
            $updateFields = implode(', ', array_map(function ($key) {
                return "$key = ?";
            }, array_keys($data)));

            $params = array_values($data);
            $params[] = $studentID;

            $updateSQL = "UPDATE Community SET $updateFields WHERE ID = ?";
            $stmt = $this->externalDb->prepareQuery($updateSQL, $params)->execute();
            if ($stmt === false) {
                throw new Exception(
                    print_r($this->externalDb->errorInfo(), true)
                );
            }
        }
    }

    private function updateStudentsTable($data, $studentID): void
    {
        if (!empty($data)) {
            $updateFields = implode(', ', array_map(function ($key) {
                return "$key = ?";
            }, array_keys($data)));

            $params = array_values($data);
            $params[] = $studentID;

            $updateSQL = "UPDATE Students SET $updateFields WHERE ID = ?";
            $stmt = $this->externalDb->prepareQuery($updateSQL, $params)->execute();
            if ($stmt === false) {
                throw new Exception(
                    print_r($this->externalDb->errorInfo(), true)
                );
            }
        }
    }

    private function updateCommunityLegalTable($data, $studentID, $modifiedBy): void
    {
        if (!empty($data)) {
            $updateFields = implode(', ', array_map(function ($key) {
                return "$key = ?";
            }, array_keys($data)));

            $params = array_values($data);
            $params[] = $modifiedBy;
            $params[] = date('Y-m-d H:i:s'); // ModifiedDate
            $params[] = $studentID;

            $updateSQL = "UPDATE CommunityLegal SET $updateFields, ModifiedBy = ?, ModifiedDate = ? WHERE ID = ?";
            $stmt = $this->externalDb->prepareQuery($updateSQL, $params)->execute();
            if ($stmt === false) {
                throw new Exception(
                    print_r($this->externalDb->errorInfo(), true)
                );
            }
        }
    }

    private function deleteStudentMedicalConditions($conditions, $studentID): void
    {
        $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_MEDICAL);
        if (!empty($conditions)) {
            foreach ($conditions as $apiSeqId) {
                $checkSQL = "SELECT * FROM {$selectedView} WHERE ID = ? AND MedicalConditionSeq = ?";
                $statement = $this->externalDb->prepareQuery($checkSQL, [$studentID, $apiSeqId]);
                $stmt = $statement->execute();
                if ($stmt === false) {
                    throw new Exception(
                        print_r($this->externalDb->errorInfo(), true)
                    );
                }

                if ($statement->rowCount() === 1) {
                    $deleteSQL = "
                        UPDATE MedicalCondition
                        SET ActiveFlag = 0,
                            ConditionEndDate = GETDATE()
                        WHERE MedicalConditionSeq = ?";
                    $stmt = $this->externalDb->prepareQuery($deleteSQL, [$studentID, $apiSeqId])->execute();
                    if ($stmt === false) {
                        throw new Exception(
                            print_r($this->externalDb->errorInfo(), true)
                        );
                    }
                }
            }
        }
    }

    private function createStudentMedicalConditions($conditions, $studentID, $modifiedBy): void
    {
        if (!empty($conditions)) {
            foreach ($conditions as $condition) {
                if (!isset($condition['MedicalConditionCode'])) {
                    throw new Exception('Medical condition code is required.');
                }

                $code = $condition['MedicalConditionCode'];
                $severity = isset($condition['SeverityCode']) ? $condition['SeverityCode'] : 'NULL';
                $details = isset($condition['ConditionDetails']) ? $condition['ConditionDetails'] : 'NULL';

                $insertSQL = "
                    INSERT INTO
                        MedicalCondition (
                                          ID,
                                          MedicalConditionCode,
                                          ActiveFlag,
                                          ConditionDetails,
                                          SeverityCode,
                                          ModifiedBy,
                                          ModifiedDate,
                                          Visibility
                    )
                    VALUES (?, ?, 1, ?, ?, ?, GETDATE(), 'B')";
                $params = [$studentID, $code, $details, $severity, $modifiedBy];
                $stmt = $this->externalDb->prepareQuery($insertSQL, $params)->execute();
                if ($stmt === false) {
                    throw new Exception(
                        print_r($this->externalDb->errorInfo(), true)
                    );
                }
            }
        }
    }

    private function deleteStudentConsents($consents, $modifiedBy): void
    {
        if (!empty($consents)) {
            $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_CONSENT);
            foreach ($consents as $consent) {
                if (!isset($consent['ConsentGivenBy'])) {
                    throw new Exception('Consent given by is required.');
                }

                if (!isset($consent['ConsentSeq'])) {
                    throw new Exception('Consent Seq is required.');
                }

                $consentGivenBy = $consent['ConsentGivenBy'];
                $consentSeq = $consent['ConsentSeq'];

                $checkSQL = "SELECT * FROM {$selectedView} WHERE AND ConsentSeq = ?";
                $statement = $this->externalDb->prepareQuery($checkSQL, [$consentSeq]);
                $stmt = $statement->execute();
                if ($stmt === false) {
                    throw new Exception(
                        print_r($this->externalDb->errorInfo(), true)
                    );
                }

                if ($statement->rowCount() === 1) {
                    $deleteSQL = "
                        UPDATE Consent
                        SET ActiveFlag = 0,
                            ConsentExpiryDate = GETDATE(),
                            ConsentGivenBy = ?,
                            ModifiedBy = ?,
                            ModifiedDate = GETDATE()
                        WHERE ConsentSeq = ?";
                    $params = [$consentGivenBy, $modifiedBy, $consentSeq];
                    $stmt = $this->externalDb->prepareQuery($deleteSQL, $params)->execute();
                    if ($stmt === false) {
                        throw new Exception(
                            print_r($this->externalDb->errorInfo(), true)
                        );
                    }
                }
            }
        }
    }

    private function createOrUpdateStudentConsents($consents, $studentID, $modifiedBy): void
    {
        if (!empty($consents)) {
            $selectedView = $this->viewSelector->getView(ViewSelector::PAPERLY_VIEW_STUDENT_CONSENT);
            foreach ($consents as $consent) {
                if (!isset($consent['ConsentGivenBy'])) {
                    throw new Exception('Consent given by is required.');

                }

                if (!isset($consent['ConsentCode'])) {
                    throw new Exception('Consent Code is required.');

                }

                $consentGivenBy = $consent['ConsentGivenBy'];
                $consentCode = $consent['ConsentCode'];

                $checkSQL = "SELECT * FROM {$selectedView} WHERE ConsentCode = ? AND ID = ?";
                $statement = $this->externalDb->prepareQuery($checkSQL, [$consentCode, $studentID]);
                $stmt = $statement->execute();
                if ($stmt === false) {
                    throw new Exception(
                        print_r($this->externalDb->errorInfo(), true)
                    );
                }

                // If the record exists and ActiveFlag is 0, update it
                if ($statement->rowCount()) {
                    foreach ($stmt as $row) {
                        if ($row["ActiveFlag"] == 0) {
                            $updateSQL = "
                                UPDATE Consent
                                SET ActiveFlag = 1,
                                    ConsentExpiryDate = NULL,
                                    ConsentGivenBy = ?,
                                    ConsentGivenDate = GETDATE(),
                                    ModifiedBy = ?,
                                    ModifiedDate = GETDATE()
                                WHERE ConsentSeq = ?";
                            $params = [$consentGivenBy, $modifiedBy, $row["ConsentSeq"]];
                            $stmt = $this->externalDb->prepareQuery($updateSQL, $params)->execute();
                            if ($stmt === false) {
                                throw new Exception(
                                    print_r($this->externalDb->errorInfo(), true)
                                );
                            }
                        }
                    }
                } else {
                    // If no record, insert a new one
                    $insertSQL = "
                        INSERT INTO
                            Consent (
                                     ID,
                                     ConsentCode,
                                     ActiveFlag,
                                     ConsentGivenBy,
                                     ConsentGivenDate,
                                     ModifiedBy,
                                     ModifiedDate
                     )
                    VALUES (?, ?, 1, ?, GETDATE(), ?, GETDATE())";
                    $params = [$studentID, $consentCode, $consentGivenBy, $modifiedBy];
                    $stmt = $this->externalDb->prepareQuery($insertSQL, $params)->execute();
                    if ($stmt === false) {
                        throw new Exception(
                            print_r($this->externalDb->errorInfo(), true)
                        );
                    }
                }
            }
        }
    }
}
