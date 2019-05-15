<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Offline_User implements External_UserInterface
{
    /**
     * @inheritdoc
     */
    public function getParent(string $externalUserId): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getParentSync(array $externalUserIds): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getParentEmails(array $synergyIds): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStaff(string $externalUserId): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, bool $boardingStatus, string $form): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList(string $externalUserId): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getCurrentSemester(string $externalUserId = null): string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear(string $externalUserId = null): string
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
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getEmail(string $externalUserId): string
    {
        // Return nothing
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
    public function getStudentUserAccountInfo(): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getParentUserAccountInfo(): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStaffUserAccountInfo(): array
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function determineBoarderType(string $boarderValue): string
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterByHouseId(string $houseID): ?string
    {
        return null;
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
     *
     * @return array
     */
    public function getStaffContactListExtended(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getStudentForms(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return [];
    }
}
