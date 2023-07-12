<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

class External_Synergetic_Wwsl_User extends External_Synergetic_User
{
    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        $student = parent::getStudent($externalUserId);
        if ($student === []) {
            return $student;
        }

        $query = "
          SELECT Addresses.Suburb
          FROM vStudents JOIN Addresses ON vStudents.AddressID = Addresses.AddressID
          WHERE vStudents.StudentID = :externalId
        ";

        $student['Suburb'] = $this->getConnector()->executeQuery($query, ['externalId' => $externalUserId])->fetchColumn();
        return $student;
    }
}
