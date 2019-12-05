<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Redis;
use External\CachingConnectorAbstract;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\User;
use Schoolbox\Resource\Storage;

class External_User extends CachingConnectorAbstract implements External_UserInterface
{

    /**
     * Constructor
     *
     * @param string $databaseName
     * @throws InvalidArgumentException
     * @throws LogicException If unable to build connector
     */
    public function __construct($databaseName = '')
    {
        // TODO inject dependencies
        parent::__construct(
            (new External_DatabaseFactory())->buildUserConnector($databaseName),
            new External_Offline_User(),
            Redis::getInstance(),
            ConfigLoader::getInstance()->get('external_cache_timeout')
        );
    }


    /**
     * @inheritdoc
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritdoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getStaff(string $externalUserId): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * {@inheritdoc}
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getSubjectList(User $user): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritdoc
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getStaffContactListExtended(): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getStudentForms(): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritdoc
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getAllYearLevels(): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }
}
