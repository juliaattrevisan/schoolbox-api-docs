<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use External\IOfflineConnector;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Offline_User implements External_UserInterface, IOfflineConnector
{
    /**
     * @inheritdoc
     */
    public function getParent(string $externalUserId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStaff(string $externalUserId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getSubjectList(User $user): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStaffContactListExtended(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStudentForms(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getAllYearLevels(): array
    {
        return [];
    }
}
