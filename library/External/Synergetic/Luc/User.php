<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

class External_Synergetic_Luc_User extends External_Synergetic_User
{
    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getStaffContactListExtended(): array
    {
        // Restrict staff categories to provided list

        $staffCategory = explode(',', $this->config->get('staff_dir_category_restrict'));

        $filter = '';
        $bindings = [];

        if (is_array($staffCategory) && $staffCategory[0] != '') {
            $count = 1;

            $filterPlaceholder = "";

            foreach ($staffCategory as $category) {
                if ($filterPlaceholder != '') {
                    $filterPlaceholder .= ', ';
                }

                $filterPlaceholder .= ":staffCategory{$count}";
                $bindings["staffCategory{$count}"] = $category;

                $count++;
            }

            $filter = " AND StaffCategory NOT IN ($filterPlaceholder)";
        }

        // Check if the custom staff position table exists, and if so use this to override
        // position titles [SB-10813]

        $queryTable = $this->getConnector()->executeQuery(
            "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'uStaffPosition'"
        );

        if ($queryTable->fetch()) {
            $select = 'uStaffPosition.JobTitle AS JobPositionDescription';
            $join = 'LEFT JOIN dbo.uStaffPosition ON vStaff.StaffID = uStaffPosition.ID';
        } else {
            $select = 'JobPositions.JobPositionDescription';
            $join = '';
        }

        // If the custom table exists, override the 'JobPositionDescription' field

        $query = "

            SELECT      vStaff.StaffID,
                        vStaff.SchoolStaffCode,
                        vStaff.StaffTitle,
                        vStaff.StaffPreferredName,
                        vStaff.StaffSurname,
                        vStaff.StaffOccupPhone,
                        vStaff.StaffOccupEmail,
                        vStaff.StaffExtension,
                        vStaff.StaffCampusDescription,
                        vStaff.StaffCategory,
                        vStaff.StaffCategoryType,
                        vStaff.StaffDefaultMobilePhone,
                        vStaff.StaffVoiceMail,
                        vStaff.StaffBuilding,
                        vStaff.StaffRoom,
                        vStaff.StaffHouse,
                        StaffDepartmentDescription as StaffDepartment,
                        JobPositions.StaffJobPositionStartDate,
                        JobPositions.StaffJobPositionEndDate,
                        Community.OccupDesc AS JobPositionDescriptionAlternate,
                        {$select}
            FROM        dbo.vStaff vStaff
            JOIN        dbo.Community ON Community.ID = vStaff.StaffID
            LEFT JOIN   dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
                        {$join}
            WHERE       vStaff.ActiveFlag = 1 AND
                        StaffCategoryDescription <> 'Staff on leave' AND
                        (JobPositions.StaffJobPositionStartDate <= CAST(GETDATE() AS DATE) OR JobPositions.StaffJobPositionStartDate IS NULL) AND
                        (JobPositions.StaffJobPositionEndDate >= CAST(GETDATE() AS DATE) OR JobPositions.StaffJobPositionEndDate IS NULL)
                        {$filter}
            ORDER BY    vStaff.StaffSurname
        ";

        return $this->getConnector()->executeQuery($query, $bindings)->fetchAll();
    }
}
