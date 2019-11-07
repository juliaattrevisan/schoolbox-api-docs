<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Profile\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\IConfigLoader;
use Schoolbox\Profile\ValidDetailsVisible;
use Schoolbox\Role;
use Schoolbox\User;
use External_UserInterface;

/**
 * This tests profile detail visibility to another user
 */
class VisibilityTest extends TestCase
{

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    /**
     * @param User $viewer
     * @param User $viewee
     * @param bool $reportStaffAccess
     * @param bool $restrictParentEmails
     * @param bool $expected
     * @dataProvider provider_isVisible
     */
    public function test_isVisible(
        User $viewer,
        User $viewee,
        bool $reportStaffAccess,
        bool $restrictParentEmails,
        bool $expected
    ): void {
        $config = $this->getMockConfigLoader();
        $config->setReportStaffAccess($reportStaffAccess);
        $config->setRestrictParentEmails($restrictParentEmails);
        $detailsVisible = new ValidDetailsVisible($this->getMockExternalUser(), $config);
        $this->assertEquals($detailsVisible->isSatisfiedBy($viewee, $viewer), $expected);
    }

    /**
     * @return array
     */
    public function provider_isVisible(): array
    {

        // viewer, viewee, visibility
        $studentRole = (new Role())->setType(Role::STUDENT);
        $staffRole   = (new Role())->setType(Role::STAFF);
        $parentRole  = (new Role())->setType(Role::PARENT);
        $guestRole   = (new Role())->setType(Role::GUEST);

        $superUser1 = (new User())->setId(1)->setFirstname('Super user 1')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff1     = (new User())->setId(2)->setFirstname('Test Staff 1')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student1   = (new User())->setId(3)->setFirstname('Test Student 1')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent1    = (new User())->setId(4)->setFirstname('Test Parent 1')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest1     = (new User())->setId(5)->setFirstname('Test Guest 1')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
        $superUser2 = (new User())->setId(6)->setFirstname('Super user 2')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff2     = (new User())->setId(7)->setFirstname('Test Staff 2')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student2   = (new User())->setId(8)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent2    = (new User())->setId(9)->setFirstname('Test Parent 2')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest2     = (new User())->setId(10)->setFirstname('Test Guest 2')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');

        // viewer, viewee, allowStaffAccess, restrictParentEmail, expected
        return [
            [$superUser1, $superUser2, false, true, true],
            [$superUser1, $staff2, false, true, true],
            [$superUser1, $student2, false, true, true],
            [$superUser1, $parent2, false, true, true],
            [$superUser1, $guest2, false, true, true],

            [$staff1, $superUser2, false, true, true],
            [$staff1, $staff2, false, true, true],
            [$staff1, $student2, false, true, true],
            [$staff1, $parent2, false, true, true],
            [$staff1, $guest2, false, true, false],

            [$student1, $superUser2, false, true, true],
            [$student1, $staff2, false, true, true],
            [$student1, $student2, false, true, false],
            [$student1, $parent2, false, true, false],
            [$student1, $guest2, false, true, false],

            [$parent1, $superUser2, false, true, false],
            [$parent1, $superUser2, true, true, true],
            [$parent1, $superUser2, false, true, false],
            [$parent1, $superUser2, true, true, true],
            [$parent1, $staff2, false, true, false],

            [$parent1, $staff2, true, true, true],
            [$parent1, $student2, true, false, false],
            [$parent1, $parent2, true, true, false],
            [$parent1, $parent2, true, false, false],
            [$parent1, $guest2, true, false, false],

            [$guest1, $superUser2, true, false, false],
            [$guest1, $staff2, true, false, false],
            [$guest1, $student2, true, false, false],
            [$guest1, $parent2, true, false, false],
            [$guest1, $guest2, true, false, false],
        ];
    }

    /**
     * @return External_UserInterface
     */
    private function getMockExternalUser(): External_UserInterface
    {
        return new Class() extends \External_User
        {
            public function __construct($databaseName = '')
            {
            }

            public function getEnrolledStudents(string $externalUserId): array
            {
                return [];
            }

            public function getParent(string $externalUserId): array
            {
                return [];
            }
        };
    }

    /**
     * @return IConfigLoader
     */
    private function getMockConfigLoader(): IConfigLoader
    {
        return new Class() implements IConfigLoader
        {

            /** @var bool */
            private $reportStaffAccess;
            /** @var bool */
            private $restrictParentEmails;

            /**
             * @param bool $allow
             */
            public function setReportStaffAccess(bool $allow): void
            {
                $this->reportStaffAccess = $allow;
            }

            /**
             * @param bool $restrict
             */
            public function setRestrictParentEmails(bool $restrict): void
            {
                $this->restrictParentEmails = $restrict;
            }

            /**
             * @inheritDoc
             */
            public function get($var)
            {
                if ($var === 'report_staff_access') {
                    return $this->reportStaffAccess;
                }
                if ($var === 'restrict_parent_emails') {
                    return $this->restrictParentEmails;
                }
                return false;
            }

            /**
             * @inheritDoc
             */
            public function dropCache()
            {
                // noop
            }
        };
    }
}
