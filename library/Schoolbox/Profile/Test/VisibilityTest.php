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
use Schoolbox\Profile\ValidEmailVisible;
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
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isDetailsVisible')]
    public function test_isDetailsVisible(
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
    public static function provider_isDetailsVisible(): array
    {

        // viewer, viewee, visibility
        $studentRole = (new Role())->setType(Role::STUDENT)->setViewStudentProfile(false)->setViewParentProfile(false);
        $staffRoleCanView   = (new Role())->setType(Role::STAFF)->setViewStudentProfile(true)->setViewParentProfile(true);
        $staffRoleCannotView   = (new Role())->setType(Role::STAFF)->setViewStudentProfile(false)->setViewParentProfile(false);
        $staffRoleStudentOnly   = (new Role())->setType(Role::STAFF)->setViewStudentProfile(true)->setViewParentProfile(false);
        $parentRole  = (new Role())->setType(Role::PARENT)->setViewStudentProfile(false)->setViewParentProfile(false);
        $guestRole   = (new Role())->setType(Role::GUEST)->setViewStudentProfile(false)->setViewParentProfile(false);

        $superUser1 = (new User())->setId(1)->setFirstname('Super user 1')->setSuperuser(true)->setRole($staffRoleCanView)->setAltEmail('-');
        $staff1     = (new User())->setId(2)->setFirstname('Test Staff 1')->setSuperuser(false)->setRole($staffRoleCanView)->setAltEmail('-');
        $student1   = (new User())->setId(3)->setFirstname('Test Student 1')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent1    = (new User())->setId(4)->setFirstname('Test Parent 1')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-')
                                  ->setExternalId('parent1');
        $guest1     = (new User())->setId(5)->setFirstname('Test Guest 1')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
        $superUser2 = (new User())->setId(6)->setFirstname('Super user 2')->setSuperuser(true)->setRole($staffRoleCanView)->setAltEmail('-');
        $staff2     = (new User())->setId(7)->setFirstname('Test Staff 2')->setSuperuser(false)->setRole($staffRoleCanView)->setAltEmail('-');
        $student2   = (new User())->setId(8)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent2    = (new User())->setId(9)->setFirstname('Test Parent 2')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest2     = (new User())->setId(10)->setFirstname('Test Guest 2')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
        $superUser3 = (new User())->setId(11)->setFirstname('Super user 3')->setSuperuser(true)->setRole($staffRoleCannotView)->setAltEmail('-');
        $staff3     = (new User())->setId(12)->setFirstname('Test Staff 3')->setSuperuser(false)->setRole($staffRoleCannotView)->setAltEmail('-');
        $staff4     = (new User())->setId(13)->setFirstname('Test Staff 4')->setSuperuser(false)->setRole($staffRoleStudentOnly)->setAltEmail('-');

        // viewer, viewee, reportStaffAccess, restrictParentEmail, expected

        $testDataset = [];
        // reportStaffAccess flag and restrictParentEmail flag should only affect parent -> parent or staff
        foreach ([true, false] as $reportStaffAccess) {
            foreach ([true, false] as $restrictParentEmail) {
                $currentDataset =
                    [
                        [$superUser1, $superUser2, $reportStaffAccess, $restrictParentEmail, true],
                        [$superUser1, $staff2, $reportStaffAccess, $restrictParentEmail, true],
                        [$superUser1, $student2, $reportStaffAccess, $restrictParentEmail, true],
                        [$superUser1, $parent2, $reportStaffAccess, $restrictParentEmail, true],
                        [$superUser1, $guest2, $reportStaffAccess, $restrictParentEmail, true],

                        [$staff1, $superUser2, $reportStaffAccess, $restrictParentEmail, true],
                        [$staff1, $staff2, $reportStaffAccess, $restrictParentEmail, true],
                        [$staff1, $student2, $reportStaffAccess, $restrictParentEmail, true],
                        [$staff1, $parent2, $reportStaffAccess, $restrictParentEmail, true],
                        [$staff1, $guest2, $reportStaffAccess, $restrictParentEmail, false],

                        [$student1, $superUser2, $reportStaffAccess, $restrictParentEmail, true],
                        [$student1, $staff2, $reportStaffAccess, $restrictParentEmail, true],
                        [$student1, $student2, $reportStaffAccess, $restrictParentEmail, false],
                        [$student1, $parent2, $reportStaffAccess, $restrictParentEmail, false],
                        [$student1, $guest2, $reportStaffAccess, $restrictParentEmail, false],

                        [$guest1, $superUser2, $reportStaffAccess, $restrictParentEmail, false],
                        [$guest1, $staff2, $reportStaffAccess, $restrictParentEmail, false],
                        [$guest1, $student2, $reportStaffAccess, $restrictParentEmail, false],
                        [$guest1, $parent2, $reportStaffAccess, $restrictParentEmail, false],
                        [$guest1, $guest2, $reportStaffAccess, $restrictParentEmail, false],

                        [$parent1, $student2, $reportStaffAccess, $restrictParentEmail, false],
                        [$parent1, $guest2, $reportStaffAccess, $restrictParentEmail, false],

                        [$superUser3, $student2, $reportStaffAccess, $restrictParentEmail, true],
                        [$staff3, $guest2, $reportStaffAccess, $restrictParentEmail, false],
                        [$staff4, $student1, $reportStaffAccess, $restrictParentEmail, true],
                        [$staff4, $parent1, $reportStaffAccess, $restrictParentEmail, false],
                    ];

                $testDataset = array_merge($testDataset, $currentDataset);
            }
        }

        // visibility for parent should respect the flags
        foreach ([true, false] as $reportStaffAccess) {
            foreach ([true, false] as $restrictParentEmail) {
                $parentDataset = [
                    [$parent1, $superUser2, $reportStaffAccess, $restrictParentEmail, $reportStaffAccess],
                    [$parent1, $staff2, $reportStaffAccess, $restrictParentEmail, $reportStaffAccess],
                    [$parent2, $parent1, $reportStaffAccess, $restrictParentEmail, !$restrictParentEmail],
                    // parent 2 is hiding his contact using SIS flag
                    [$parent1, $parent2, $reportStaffAccess, $restrictParentEmail, false],
                ];
                $testDataset   = array_merge($testDataset, $parentDataset);
            }
        }
        return $testDataset;
    }


    /**
     * The full set of possibilities. There are hundreds but they're easy to manually verify.
     * This set accounts for people viewing themselves as well as viewing others.
     *
     * @param User $viewer
     * @param User $viewee
     * @param bool $reportStaffAccess
     * @param bool $restrictParentEmails
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isDetailsVisibleComprehensive')]
    public function test_isDetailsVisibleComprehensive(
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
     * These were programmatically generated outcomes.
     *
     * Generator.php was used to manufacture the cartesian product of possible outcomes;
     * the results of that generation are returned here
     *
     * @return array
     */
    public static function provider_isDetailsVisibleComprehensive(): array
    {
        // viewer, viewee, visibility
        $studentRole = (new Role())->setType(Role::STUDENT)->setViewStudentProfile(false)->setViewParentProfile(false);
        $staffRole   = (new Role())->setType(Role::STAFF)->setViewStudentProfile(true)->setViewParentProfile(true);
        $parentRole  = (new Role())->setType(Role::PARENT)->setViewStudentProfile(false)->setViewParentProfile(false);
        $guestRole   = (new Role())->setType(Role::GUEST)->setViewStudentProfile(false)->setViewParentProfile(false);

        $superUser1 = (new User())->setId(1)->setFirstname('Super user 1')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff1     = (new User())->setId(2)->setFirstname('Test Staff 1')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student1   = (new User())->setId(3)->setFirstname('Test Student 1')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent1    = (new User())->setId(4)->setFirstname('Test Parent 1')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-')
                                  ->setExternalId('parent1');
        $guest1     = (new User())->setId(5)->setFirstname('Test Guest 1')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
        $superUser2 = (new User())->setId(6)->setFirstname('Super user 2')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff2     = (new User())->setId(7)->setFirstname('Test Staff 2')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student2   = (new User())->setId(8)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent2    = (new User())->setId(9)->setFirstname('Test Parent 2')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest2     = (new User())->setId(10)->setFirstname('Test Guest 2')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');

        return [
            /* Students can always view their own details
             */
            [$student1, $student1, true, true, true],
            [$student1, $student1, false, true, true],
            [$student1, $student1, true, false, true],
            [$student1, $student1, false, false, true],
            [$student2, $student2, true, true, true],
            [$student2, $student2, false, true, true],
            [$student2, $student2, true, false, true],
            [$student2, $student2, false, false, true],

            /* Students cannot see another student's details
             */
            [$student1, $student2, true, true, false],
            [$student1, $student2, false, true, false],
            [$student1, $student2, true, false, false],
            [$student1, $student2, false, false, false],
            [$student2, $student1, true, true, false],
            [$student2, $student1, false, true, false],
            [$student2, $student1, true, false, false],
            [$student2, $student1, false, false, false],

            /* Students cannot see parents' details
             */
            [$student1, $parent1, true, true, false],
            [$student1, $parent1, false, true, false],
            [$student1, $parent1, true, false, false],
            [$student1, $parent1, false, false, false],
            [$student1, $parent2, true, true, false],
            [$student1, $parent2, false, true, false],
            [$student1, $parent2, true, false, false],
            [$student1, $parent2, false, false, false],
            [$student2, $parent1, true, true, false],
            [$student2, $parent1, false, true, false],
            [$student2, $parent1, true, false, false],
            [$student2, $parent1, false, false, false],
            [$student2, $parent2, true, true, false],
            [$student2, $parent2, false, true, false],
            [$student2, $parent2, true, false, false],
            [$student2, $parent2, false, false, false],

            /* Students can see superusers' details
             */
            [$student1, $superUser1, true, true, true],
            [$student1, $superUser1, false, true, true],
            [$student1, $superUser1, true, false, true],
            [$student1, $superUser1, false, false, true],
            [$student1, $superUser2, true, true, true],
            [$student1, $superUser2, false, true, true],
            [$student1, $superUser2, true, false, true],
            [$student1, $superUser2, false, false, true],
            [$student2, $superUser1, true, true, true],
            [$student2, $superUser1, false, true, true],
            [$student2, $superUser1, true, false, true],
            [$student2, $superUser1, false, false, true],
            [$student2, $superUser2, true, true, true],
            [$student2, $superUser2, false, true, true],
            [$student2, $superUser2, true, false, true],
            [$student2, $superUser2, false, false, true],

            /* Students can see staff's details
             */
            [$student1, $staff1, true, true, true],
            [$student1, $staff1, false, true, true],
            [$student1, $staff1, true, false, true],
            [$student1, $staff1, false, false, true],
            [$student1, $staff2, true, true, true],
            [$student1, $staff2, false, true, true],
            [$student1, $staff2, true, false, true],
            [$student1, $staff2, false, false, true],
            [$student2, $staff1, true, true, true],
            [$student2, $staff1, false, true, true],
            [$student2, $staff1, true, false, true],
            [$student2, $staff1, false, false, true],
            [$student2, $staff2, true, true, true],
            [$student2, $staff2, false, true, true],
            [$student2, $staff2, true, false, true],
            [$student2, $staff2, false, false, true],

            /* Students cannot see guests' details
             */
            [$student1, $guest1, true, true, false],
            [$student1, $guest1, false, true, false],
            [$student1, $guest1, true, false, false],
            [$student1, $guest1, false, false, false],
            [$student1, $guest2, true, true, false],
            [$student1, $guest2, false, true, false],
            [$student1, $guest2, true, false, false],
            [$student1, $guest2, false, false, false],
            [$student2, $guest1, true, true, false],
            [$student2, $guest1, false, true, false],
            [$student2, $guest1, true, false, false],
            [$student2, $guest1, false, false, false],
            [$student2, $guest2, true, true, false],
            [$student2, $guest2, false, true, false],
            [$student2, $guest2, true, false, false],
            [$student2, $guest2, false, false, false],

            /* Parents cannot see students' details
             */
            [$parent1, $student1, true, true, false],
            [$parent1, $student1, false, true, false],
            [$parent1, $student1, true, false, false],
            [$parent1, $student1, false, false, false],
            [$parent1, $student2, true, true, false],
            [$parent1, $student2, false, true, false],
            [$parent1, $student2, true, false, false],
            [$parent1, $student2, false, false, false],
            [$parent2, $student1, true, true, false],
            [$parent2, $student1, false, true, false],
            [$parent2, $student1, true, false, false],
            [$parent2, $student1, false, false, false],
            [$parent2, $student2, true, true, false],
            [$parent2, $student2, false, true, false],
            [$parent2, $student2, true, false, false],
            [$parent2, $student2, false, false, false],

            /* Parents can see their own details
             */
            [$parent1, $parent1, true, true, true],
            [$parent1, $parent1, false, true, true],
            [$parent1, $parent1, true, false, true],
            [$parent1, $parent1, false, false, true],
            [$parent2, $parent2, true, true, true],
            [$parent2, $parent2, false, true, true],
            [$parent2, $parent2, true, false, true],
            [$parent2, $parent2, false, false, true],

            /* Parents cannot see another parents' details
             * unless restrictParentEmails is off
             */
            [$parent1, $parent2, true, true, false],
            [$parent1, $parent2, false, true, false],
            [$parent1, $parent2, true, false, false],
            [$parent1, $parent2, false, false, false],

            /* Parents cannot see another parents' details
             * unless restrictParentEmails is off
             * Note that parent1 is configured differently to parent2
             */
            [$parent2, $parent1, true, true, false],
            [$parent2, $parent1, false, true, false],
            [$parent2, $parent1, true, false, true],
            [$parent2, $parent1, false, false, true],

            /* Parents can see superuser's details
             * But only if ReportStaffAccess is true
             */
            [$parent1, $superUser1, true, true, true],
            [$parent1, $superUser1, false, true, false],
            [$parent1, $superUser1, true, false, true],
            [$parent1, $superUser1, false, false, false],
            [$parent1, $superUser2, true, true, true],
            [$parent1, $superUser2, false, true, false],
            [$parent1, $superUser2, true, false, true],
            [$parent1, $superUser2, false, false, false],
            [$parent2, $superUser1, true, true, true],
            [$parent2, $superUser1, false, true, false],
            [$parent2, $superUser1, true, false, true],
            [$parent2, $superUser1, false, false, false],
            [$parent2, $superUser2, true, true, true],
            [$parent2, $superUser2, false, true, false],
            [$parent2, $superUser2, true, false, true],
            [$parent2, $superUser2, false, false, false],

            /* Parents can see staff details
             * but only if ReportStaffAccess is true
             */
            [$parent1, $staff1, true, true, true],
            [$parent1, $staff1, false, true, false],
            [$parent1, $staff1, true, false, true],
            [$parent1, $staff1, false, false, false],
            [$parent1, $staff2, true, true, true],
            [$parent1, $staff2, false, true, false],
            [$parent1, $staff2, true, false, true],
            [$parent1, $staff2, false, false, false],
            [$parent2, $staff1, true, true, true],
            [$parent2, $staff1, false, true, false],
            [$parent2, $staff1, true, false, true],
            [$parent2, $staff1, false, false, false],
            [$parent2, $staff2, true, true, true],
            [$parent2, $staff2, false, true, false],
            [$parent2, $staff2, true, false, true],
            [$parent2, $staff2, false, false, false],

            /* Parents cannot see guests' details
             */
            [$parent1, $guest1, true, true, false],
            [$parent1, $guest1, false, true, false],
            [$parent1, $guest1, true, false, false],
            [$parent1, $guest1, false, false, false],
            [$parent1, $guest2, true, true, false],
            [$parent1, $guest2, false, true, false],
            [$parent1, $guest2, true, false, false],
            [$parent1, $guest2, false, false, false],
            [$parent2, $guest1, true, true, false],
            [$parent2, $guest1, false, true, false],
            [$parent2, $guest1, true, false, false],
            [$parent2, $guest1, false, false, false],
            [$parent2, $guest2, true, true, false],
            [$parent2, $guest2, false, true, false],
            [$parent2, $guest2, true, false, false],
            [$parent2, $guest2, false, false, false],

            /* Superusers can see student details
             */
            [$superUser1, $student1, true, true, true],
            [$superUser1, $student1, false, true, true],
            [$superUser1, $student1, true, false, true],
            [$superUser1, $student1, false, false, true],
            [$superUser1, $student2, true, true, true],
            [$superUser1, $student2, false, true, true],
            [$superUser1, $student2, true, false, true],
            [$superUser1, $student2, false, false, true],
            [$superUser2, $student1, true, true, true],
            [$superUser2, $student1, false, true, true],
            [$superUser2, $student1, true, false, true],
            [$superUser2, $student1, false, false, true],
            [$superUser2, $student2, true, true, true],
            [$superUser2, $student2, false, true, true],
            [$superUser2, $student2, true, false, true],
            [$superUser2, $student2, false, false, true],

            /* Superusers can see parents' details
             */
            [$superUser1, $parent1, true, true, true],
            [$superUser1, $parent1, false, true, true],
            [$superUser1, $parent1, true, false, true],
            [$superUser1, $parent1, false, false, true],
            [$superUser1, $parent2, true, true, true],
            [$superUser1, $parent2, false, true, true],
            [$superUser1, $parent2, true, false, true],
            [$superUser1, $parent2, false, false, true],
            [$superUser2, $parent1, true, true, true],
            [$superUser2, $parent1, false, true, true],
            [$superUser2, $parent1, true, false, true],
            [$superUser2, $parent1, false, false, true],
            [$superUser2, $parent2, true, true, true],
            [$superUser2, $parent2, false, true, true],
            [$superUser2, $parent2, true, false, true],
            [$superUser2, $parent2, false, false, true],

            /* Superusers can see superusers' details
             */
            [$superUser1, $superUser1, true, true, true],
            [$superUser1, $superUser1, false, true, true],
            [$superUser1, $superUser1, true, false, true],
            [$superUser1, $superUser1, false, false, true],
            [$superUser1, $superUser2, true, true, true],
            [$superUser1, $superUser2, false, true, true],
            [$superUser1, $superUser2, true, false, true],
            [$superUser1, $superUser2, false, false, true],
            [$superUser2, $superUser1, true, true, true],
            [$superUser2, $superUser1, false, true, true],
            [$superUser2, $superUser1, true, false, true],
            [$superUser2, $superUser1, false, false, true],
            [$superUser2, $superUser2, true, true, true],
            [$superUser2, $superUser2, false, true, true],
            [$superUser2, $superUser2, true, false, true],
            [$superUser2, $superUser2, false, false, true],

            /* Superusers can see staff details
             */
            [$superUser1, $staff1, true, true, true],
            [$superUser1, $staff1, false, true, true],
            [$superUser1, $staff1, true, false, true],
            [$superUser1, $staff1, false, false, true],
            [$superUser1, $staff2, true, true, true],
            [$superUser1, $staff2, false, true, true],
            [$superUser1, $staff2, true, false, true],
            [$superUser1, $staff2, false, false, true],
            [$superUser2, $staff1, true, true, true],
            [$superUser2, $staff1, false, true, true],
            [$superUser2, $staff1, true, false, true],
            [$superUser2, $staff1, false, false, true],
            [$superUser2, $staff2, true, true, true],
            [$superUser2, $staff2, false, true, true],
            [$superUser2, $staff2, true, false, true],
            [$superUser2, $staff2, false, false, true],

            /* Superusers can see guests' details
             */
            [$superUser1, $guest1, true, true, true],
            [$superUser1, $guest1, false, true, true],
            [$superUser1, $guest1, true, false, true],
            [$superUser1, $guest1, false, false, true],
            [$superUser1, $guest2, true, true, true],
            [$superUser1, $guest2, false, true, true],
            [$superUser1, $guest2, true, false, true],
            [$superUser1, $guest2, false, false, true],
            [$superUser2, $guest1, true, true, true],
            [$superUser2, $guest1, false, true, true],
            [$superUser2, $guest1, true, false, true],
            [$superUser2, $guest1, false, false, true],
            [$superUser2, $guest2, true, true, true],
            [$superUser2, $guest2, false, true, true],
            [$superUser2, $guest2, true, false, true],
            [$superUser2, $guest2, false, false, true],

            /* Staff can see student details
             */
            [$staff1, $student1, true, true, true],
            [$staff1, $student1, false, true, true],
            [$staff1, $student1, true, false, true],
            [$staff1, $student1, false, false, true],
            [$staff1, $student2, true, true, true],
            [$staff1, $student2, false, true, true],
            [$staff1, $student2, true, false, true],
            [$staff1, $student2, false, false, true],
            [$staff2, $student1, true, true, true],
            [$staff2, $student1, false, true, true],
            [$staff2, $student1, true, false, true],
            [$staff2, $student1, false, false, true],
            [$staff2, $student2, true, true, true],
            [$staff2, $student2, false, true, true],
            [$staff2, $student2, true, false, true],
            [$staff2, $student2, false, false, true],

            /* Staff can see parent details
             */
            [$staff1, $parent1, true, true, true],
            [$staff1, $parent1, false, true, true],
            [$staff1, $parent1, true, false, true],
            [$staff1, $parent1, false, false, true],
            [$staff1, $parent2, true, true, true],
            [$staff1, $parent2, false, true, true],
            [$staff1, $parent2, true, false, true],
            [$staff1, $parent2, false, false, true],
            [$staff2, $parent1, true, true, true],
            [$staff2, $parent1, false, true, true],
            [$staff2, $parent1, true, false, true],
            [$staff2, $parent1, false, false, true],
            [$staff2, $parent2, true, true, true],
            [$staff2, $parent2, false, true, true],
            [$staff2, $parent2, true, false, true],
            [$staff2, $parent2, false, false, true],

            /* Staff can see superuser details
             */
            [$staff1, $superUser1, true, true, true],
            [$staff1, $superUser1, false, true, true],
            [$staff1, $superUser1, true, false, true],
            [$staff1, $superUser1, false, false, true],
            [$staff1, $superUser2, true, true, true],
            [$staff1, $superUser2, false, true, true],
            [$staff1, $superUser2, true, false, true],
            [$staff1, $superUser2, false, false, true],
            [$staff2, $superUser1, true, true, true],
            [$staff2, $superUser1, false, true, true],
            [$staff2, $superUser1, true, false, true],
            [$staff2, $superUser1, false, false, true],
            [$staff2, $superUser2, true, true, true],
            [$staff2, $superUser2, false, true, true],
            [$staff2, $superUser2, true, false, true],
            [$staff2, $superUser2, false, false, true],

            /* Staff can see their own details
             */
            [$staff1, $staff1, true, true, true],
            [$staff1, $staff1, false, true, true],
            [$staff1, $staff1, true, false, true],
            [$staff1, $staff1, false, false, true],
            [$staff2, $staff2, true, true, true],
            [$staff2, $staff2, false, true, true],
            [$staff2, $staff2, true, false, true],
            [$staff2, $staff2, false, false, true],

            /* Staff can see staff details
             */
            [$staff1, $staff2, true, true, true],
            [$staff1, $staff2, false, true, true],
            [$staff1, $staff2, true, false, true],
            [$staff1, $staff2, false, false, true],
            [$staff2, $staff1, true, true, true],
            [$staff2, $staff1, false, true, true],
            [$staff2, $staff1, true, false, true],
            [$staff2, $staff1, false, false, true],

            /* Staff cannot see guest details
             */
            [$staff1, $guest1, true, true, false],
            [$staff1, $guest1, false, true, false],
            [$staff1, $guest1, true, false, false],
            [$staff1, $guest1, false, false, false],
            [$staff1, $guest2, true, true, false],
            [$staff1, $guest2, false, true, false],
            [$staff1, $guest2, true, false, false],
            [$staff1, $guest2, false, false, false],
            [$staff2, $guest1, true, true, false],
            [$staff2, $guest1, false, true, false],
            [$staff2, $guest1, true, false, false],
            [$staff2, $guest1, false, false, false],
            [$staff2, $guest2, true, true, false],
            [$staff2, $guest2, false, true, false],
            [$staff2, $guest2, true, false, false],
            [$staff2, $guest2, false, false, false],

            /* Guests cannot see student details
             */
            [$guest1, $student1, true, true, false],
            [$guest1, $student1, false, true, false],
            [$guest1, $student1, true, false, false],
            [$guest1, $student1, false, false, false],
            [$guest1, $student2, true, true, false],
            [$guest1, $student2, false, true, false],
            [$guest1, $student2, true, false, false],
            [$guest1, $student2, false, false, false],
            [$guest2, $student1, true, true, false],
            [$guest2, $student1, false, true, false],
            [$guest2, $student1, true, false, false],
            [$guest2, $student1, false, false, false],
            [$guest2, $student2, true, true, false],
            [$guest2, $student2, false, true, false],
            [$guest2, $student2, true, false, false],
            [$guest2, $student2, false, false, false],

            /* Guests cannot see parent details
             */
            [$guest1, $parent1, true, true, false],
            [$guest1, $parent1, false, true, false],
            [$guest1, $parent1, true, false, false],
            [$guest1, $parent1, false, false, false],
            [$guest1, $parent2, true, true, false],
            [$guest1, $parent2, false, true, false],
            [$guest1, $parent2, true, false, false],
            [$guest1, $parent2, false, false, false],
            [$guest2, $parent1, true, true, false],
            [$guest2, $parent1, false, true, false],
            [$guest2, $parent1, true, false, false],
            [$guest2, $parent1, false, false, false],
            [$guest2, $parent2, true, true, false],
            [$guest2, $parent2, false, true, false],
            [$guest2, $parent2, true, false, false],
            [$guest2, $parent2, false, false, false],

            /* Guests cannot see superuser details
             */
            [$guest1, $superUser1, true, true, false],
            [$guest1, $superUser1, false, true, false],
            [$guest1, $superUser1, true, false, false],
            [$guest1, $superUser1, false, false, false],
            [$guest1, $superUser2, true, true, false],
            [$guest1, $superUser2, false, true, false],
            [$guest1, $superUser2, true, false, false],
            [$guest1, $superUser2, false, false, false],
            [$guest2, $superUser1, true, true, false],
            [$guest2, $superUser1, false, true, false],
            [$guest2, $superUser1, true, false, false],
            [$guest2, $superUser1, false, false, false],
            [$guest2, $superUser2, true, true, false],
            [$guest2, $superUser2, false, true, false],
            [$guest2, $superUser2, true, false, false],
            [$guest2, $superUser2, false, false, false],

            /* Guests cannot see staff details
             */
            [$guest1, $staff1, true, true, false],
            [$guest1, $staff1, false, true, false],
            [$guest1, $staff1, true, false, false],
            [$guest1, $staff1, false, false, false],
            [$guest1, $staff2, true, true, false],
            [$guest1, $staff2, false, true, false],
            [$guest1, $staff2, true, false, false],
            [$guest1, $staff2, false, false, false],
            [$guest2, $staff1, true, true, false],
            [$guest2, $staff1, false, true, false],
            [$guest2, $staff1, true, false, false],
            [$guest2, $staff1, false, false, false],
            [$guest2, $staff2, true, true, false],
            [$guest2, $staff2, false, true, false],
            [$guest2, $staff2, true, false, false],
            [$guest2, $staff2, false, false, false],

            /* Guests cannot see guest details
             */
            [$guest1, $guest1, true, true, false],
            [$guest1, $guest1, false, true, false],
            [$guest1, $guest1, true, false, false],
            [$guest1, $guest1, false, false, false],
            [$guest1, $guest2, true, true, false],
            [$guest1, $guest2, false, true, false],
            [$guest1, $guest2, true, false, false],
            [$guest1, $guest2, false, false, false],
            [$guest2, $guest1, true, true, false],
            [$guest2, $guest1, false, true, false],
            [$guest2, $guest1, true, false, false],
            [$guest2, $guest1, false, false, false],
            [$guest2, $guest2, true, true, false],
            [$guest2, $guest2, false, true, false],
            [$guest2, $guest2, true, false, false],
            [$guest2, $guest2, false, false, false],
        ];
    }


    /**
     * Email and Detail visibility, though often similar, aren't always the same
     * Students viewing other students allows them to see each other's email, but not details
     *
     * @param User $viewer
     * @param User $viewee
     * @param bool $reportStaffAccess
     * @param bool $restrictParentEmails
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isEmailVisible')]
    public function test_isEmailVisible(
        User $viewer,
        User $viewee,
        bool $reportStaffAccess,
        bool $restrictParentEmails,
        bool $expected
    ): void {
        $config = $this->getMockConfigLoader();
        $config->setReportStaffAccess($reportStaffAccess);
        $config->setRestrictParentEmails($restrictParentEmails);
        $emailVisible = new ValidEmailVisible($this->getMockExternalUser(), $config);
        $this->assertEquals($emailVisible->isSatisfiedBy($viewee, $viewer), $expected);
    }


    /**
     * @return array
     */
    public static function provider_isEmailVisible(): array
    {
        // viewer, viewee, visibility
        $studentRole = (new Role())->setType(Role::STUDENT)->setViewStudentProfile(false)->setViewParentProfile(false);

        $student1   = (new User())->setId(3)->setFirstname('Test Student 1')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $student1a   = (new User())->setId(3)->setFirstname('Test Student 1a')->setSuperuser(false)->setRole($studentRole)->setAltEmail('');
        $student2   = (new User())->setId(8)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $student2a   = (new User())->setId(8)->setFirstname('Test Student 2a')->setSuperuser(false)->setRole($studentRole)->setAltEmail('');

        return [

            /* Exit early when no email
             */
            [$student1a, $student2a, true, true, false],
            [$student1a, $student2a, true, false, false],
            [$student1a, $student2a, false, true, false],
            [$student1a, $student2a, false, false, false],
            [$student1a, $student1a, true, true, false],
            [$student1a, $student1a, true, false, false],
            [$student1a, $student1a, false, true, false],
            [$student1a, $student1a, false, false, false],
            [$student2a, $student1a, true, true, false],
            [$student2a, $student1a, true, false, false],
            [$student2a, $student1a, false, true, false],
            [$student2a, $student1a, false, false, false],
            [$student2a, $student2a, true, true, false],
            [$student2a, $student2a, true, false, false],
            [$student2a, $student2a, false, true, false],
            [$student2a, $student2a, false, false, false],

            /* Students viewing other students
             */
            [$student1, $student2, true, true, true],
            [$student1, $student2, true, false, true],
            [$student1, $student2, false, true, true],
            [$student1, $student2, false, false, true],
            [$student2, $student1, true, true, true],
            [$student2, $student1, true, false, true],
            [$student2, $student1, false, true, true],
            [$student2, $student1, false, false, true],
        ];
    }


    /**
     * @return External_UserInterface
     */
    private function getMockExternalUser(): External_UserInterface
    {
        return new Class () extends \External_User
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
                if ($externalUserId === 'parent1') {
                    return ['DirectoryIncludeFlag' => true];
                }
                return [];
            }
        };
    }

    /**
     * @return IConfigLoader
     */
    private function getMockConfigLoader(): IConfigLoader
    {
        return new Class () implements IConfigLoader
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
