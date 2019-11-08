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
        $parent1    = (new User())->setId(4)->setFirstname('Test Parent 1')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-')
                                  ->setExternalId('parent1');
        $guest1     = (new User())->setId(5)->setFirstname('Test Guest 1')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
        $superUser2 = (new User())->setId(6)->setFirstname('Super user 2')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff2     = (new User())->setId(7)->setFirstname('Test Staff 2')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student2   = (new User())->setId(8)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent2    = (new User())->setId(9)->setFirstname('Test Parent 2')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest2     = (new User())->setId(10)->setFirstname('Test Guest 2')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');

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
     * @dataProvider provider_isDetailsVisibleComprehensive
     */
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
    public function provider_isDetailsVisibleComprehensive(): array
    {
        // viewer, viewee, visibility
        $studentRole = (new Role())->setType(Role::STUDENT);
        $staffRole   = (new Role())->setType(Role::STAFF);
        $parentRole  = (new Role())->setType(Role::PARENT);
        $guestRole   = (new Role())->setType(Role::GUEST);

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

            /* Students cannot see another student's details
             */
            [$student1, $student2, true, true, false],
            [$student1, $student2, false, true, false],
            [$student1, $student2, true, false, false],
            [$student1, $student2, false, false, false],

            /* Parents cannot see students' details
             */
            [$student1, $parent1, true, true, false],
            [$student1, $parent1, false, true, false],
            [$student1, $parent1, true, false, false],
            [$student1, $parent1, false, false, false],
            [$student1, $parent2, true, true, false],
            [$student1, $parent2, false, true, false],
            [$student1, $parent2, true, false, false],
            [$student1, $parent2, false, false, false],

            /* Superusers can see students' details
             */
            [$student1, $superUser1, true, true, true],
            [$student1, $superUser1, false, true, true],
            [$student1, $superUser1, true, false, true],
            [$student1, $superUser1, false, false, true],
            [$student1, $superUser2, true, true, true],
            [$student1, $superUser2, false, true, true],
            [$student1, $superUser2, true, false, true],
            [$student1, $superUser2, false, false, true],

            /* Staff can see students' details
             */
            [$student1, $staff1, true, true, true],
            [$student1, $staff1, false, true, true],
            [$student1, $staff1, true, false, true],
            [$student1, $staff1, false, false, true],
            [$student1, $staff2, true, true, true],
            [$student1, $staff2, false, true, true],
            [$student1, $staff2, true, false, true],
            [$student1, $staff2, false, false, true],

            /* Guests cannot see students' details
             */
            [$student1, $guest1, true, true, false],
            [$student1, $guest1, false, true, false],
            [$student1, $guest1, true, false, false],
            [$student1, $guest1, false, false, false],
            [$student1, $guest2, true, true, false],
            [$student1, $guest2, false, true, false],
            [$student1, $guest2, true, false, false],
            [$student1, $guest2, false, false, false],

            /* Students cannot see another student's details
             */
            [$student2, $student1, true, true, false],
            [$student2, $student1, false, true, false],
            [$student2, $student1, true, false, false],
            [$student2, $student1, false, false, false],
            [$student2, $student2, true, true, true],
            [$student2, $student2, false, true, true],
            [$student2, $student2, true, false, true],
            [$student2, $student2, false, false, true],

            /* Parents cannot see students' details
             */
            [$student2, $parent1, true, true, false],
            [$student2, $parent1, false, true, false],
            [$student2, $parent1, true, false, false],
            [$student2, $parent1, false, false, false],
            [$student2, $parent2, true, true, false],
            [$student2, $parent2, false, true, false],
            [$student2, $parent2, true, false, false],
            [$student2, $parent2, false, false, false],

            /* Superusers can see students' details
             */
            [$student2, $superUser1, true, true, true],
            [$student2, $superUser1, false, true, true],
            [$student2, $superUser1, true, false, true],
            [$student2, $superUser1, false, false, true],
            [$student2, $superUser2, true, true, true],
            [$student2, $superUser2, false, true, true],
            [$student2, $superUser2, true, false, true],
            [$student2, $superUser2, false, false, true],

            /* Staff can see students' details
             */
            [$student2, $staff1, true, true, true],
            [$student2, $staff1, false, true, true],
            [$student2, $staff1, true, false, true],
            [$student2, $staff1, false, false, true],
            [$student2, $staff2, true, true, true],
            [$student2, $staff2, false, true, true],
            [$student2, $staff2, true, false, true],
            [$student2, $staff2, false, false, true],

            /* Guests cannot see students' details
             */
            [$student2, $guest1, true, true, false],
            [$student2, $guest1, false, true, false],
            [$student2, $guest1, true, false, false],
            [$student2, $guest1, false, false, false],
            [$student2, $guest2, true, true, false],
            [$student2, $guest2, false, true, false],
            [$student2, $guest2, true, false, false],
            [$student2, $guest2, false, false, false],

            /* Students cannot see parents' details
             */
            [$parent1, $student1, true, true, false],
            [$parent1, $student1, false, true, false],
            [$parent1, $student1, true, false, false],
            [$parent1, $student1, false, false, false],
            [$parent1, $student2, true, true, false],
            [$parent1, $student2, false, true, false],
            [$parent1, $student2, true, false, false],
            [$parent1, $student2, false, false, false],

            /* Parents can see their own details
             */
            [$parent1, $parent1, true, true, true],
            [$parent1, $parent1, false, true, true],
            [$parent1, $parent1, true, false, true],
            [$parent1, $parent1, false, false, true],

            /* Parents cannot see another parents' details
             */
            [$parent1, $parent2, true, true, false],
            [$parent1, $parent2, false, true, false],
            [$parent1, $parent2, true, false, false],
            [$parent1, $parent2, false, false, false],

            /* Superusers can see parents' details
             */
            [$parent1, $superUser1, true, true, true],
//            [$parent1, $superUser1, false, true, true],
            [$parent1, $superUser1, true, false, true],
//            [$parent1, $superUser1, false, false, true],
            [$parent1, $superUser2, true, true, true],
//            [$parent1, $superUser2, false, true, true],
            [$parent1, $superUser2, true, false, true],
//            [$parent1, $superUser2, false, false, true],

            /* Staff can see parents' details
             * but only if restrictParentEmails is false
             */
//            [$parent1, $staff1, true, true, false],
//            [$parent1, $staff1, false, true, false],
//            [$parent1, $staff1, true, false, true],
//            [$parent1, $staff1, false, false, true],
//            [$parent1, $staff2, true, true, false],
//            [$parent1, $staff2, false, true, false],
//            [$parent1, $staff2, true, false, true],
//            [$parent1, $staff2, false, false, true],

            /* Guests cannot see parents' details
             */
            [$parent1, $guest1, true, true, false],
            [$parent1, $guest1, false, true, false],
            [$parent1, $guest1, true, false, false],
            [$parent1, $guest1, false, false, false],
            [$parent1, $guest2, true, true, false],
            [$parent1, $guest2, false, true, false],
            [$parent1, $guest2, true, false, false],
            [$parent1, $guest2, false, false, false],

            /* Students cannot see parents' details
             */
            [$parent2, $student1, true, true, false],
            [$parent2, $student1, false, true, false],
            [$parent2, $student1, true, false, false],
            [$parent2, $student1, false, false, false],
            [$parent2, $student2, true, true, false],
            [$parent2, $student2, false, true, false],
            [$parent2, $student2, true, false, false],
            [$parent2, $student2, false, false, false],

            /* Parents cannot see another parents' details
             * unless restrictParentEmails is off
             */
            [$parent2, $parent1, true, true, false],
            [$parent2, $parent1, false, true, false],
            [$parent2, $parent1, true, false, true],
            [$parent2, $parent1, false, false, true],

            /* Parents can see their own details
             */
            [$parent2, $parent2, true, true, true],
            [$parent2, $parent2, false, true, true],
            [$parent2, $parent2, true, false, true],
            [$parent2, $parent2, false, false, true],

            /* Superusers can see parents' details
              */
            [$parent2, $superUser1, true, true, true],
//            [$parent2, $superUser1, false, true, true],
            [$parent2, $superUser1, true, false, true],
//            [$parent2, $superUser1, false, false, true],
            [$parent2, $superUser2, true, true, true],
//            [$parent2, $superUser2, false, true, true],
            [$parent2, $superUser2, true, false, true],
//            [$parent2, $superUser2, false, false, true],

            /* Staff can see parents' details
             * only if restrictParentEmails is off
             */
//            [$parent2, $staff1, true, true, false],
//            [$parent2, $staff1, false, true, false],
            [$parent2, $staff1, true, false, true],
//            [$parent2, $staff1, false, false, true],
//            [$parent2, $staff2, true, true, false],
//            [$parent2, $staff2, false, true, false],
            [$parent2, $staff2, true, false, true],
//            [$parent2, $staff2, false, false, true],

            /* Guests cannot see parents' details
             */
            [$parent2, $guest1, true, true, false],
            [$parent2, $guest1, false, true, false],
            [$parent2, $guest1, true, false, false],
            [$parent2, $guest1, false, false, false],
            [$parent2, $guest2, true, true, false],
            [$parent2, $guest2, false, true, false],
            [$parent2, $guest2, true, false, false],
            [$parent2, $guest2, false, false, false],

            /* Students can see superuser details
             */
            [$superUser1, $student1, true, true, true],
            [$superUser1, $student1, false, true, true],
            [$superUser1, $student1, true, false, true],
            [$superUser1, $student1, false, false, true],
            [$superUser1, $student2, true, true, true],
            [$superUser1, $student2, false, true, true],
            [$superUser1, $student2, true, false, true],
            [$superUser1, $student2, false, false, true],

            /* Parents can see superuser details
             * only if reportStaffAccess is true
             */
            [$superUser1, $parent1, true, true, true],
            [$superUser1, $parent1, false, true, true],
//            [$superUser1, $parent1, true, false, false],
//            [$superUser1, $parent1, false, false, false],
            [$superUser1, $parent2, true, true, true],
            [$superUser1, $parent2, false, true, true],
//            [$superUser1, $parent2, true, false, false],
//            [$superUser1, $parent2, false, false, false],

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

            /* Staff can see superusers' details
             */
            [$superUser1, $staff1, true, true, true],
            [$superUser1, $staff1, false, true, true],
            [$superUser1, $staff1, true, false, true],
            [$superUser1, $staff1, false, false, true],
            [$superUser1, $staff2, true, true, true],
            [$superUser1, $staff2, false, true, true],
            [$superUser1, $staff2, true, false, true],
            [$superUser1, $staff2, false, false, true],

            /* Guests cannot see superuser's details
             */
//            [$superUser1, $guest1, true, true, false],
//            [$superUser1, $guest1, false, true, false],
//            [$superUser1, $guest1, true, false, false],
//            [$superUser1, $guest1, false, false, false],
//            [$superUser1, $guest2, true, true, false],
//            [$superUser1, $guest2, false, true, false],
//            [$superUser1, $guest2, true, false, false],
//            [$superUser1, $guest2, false, false, false],

            /* Students can see superuser details
             */
            [$superUser2, $student1, true, true, true],
            [$superUser2, $student1, false, true, true],
            [$superUser2, $student1, true, false, true],
            [$superUser2, $student1, false, false, true],
            [$superUser2, $student2, true, true, true],
            [$superUser2, $student2, false, true, true],
            [$superUser2, $student2, true, false, true],
            [$superUser2, $student2, false, false, true],

            /* Parents can see superuser details
             * if reportStaffAccess is true
             */
//            [$superUser2, $parent1, true, true, true],
//            [$superUser2, $parent1, false, true, true],
//            [$superUser2, $parent1, true, false, false],
//            [$superUser2, $parent1, false, false, false],
//            [$superUser2, $parent2, true, true, true],
//            [$superUser2, $parent2, false, true, true],
//            [$superUser2, $parent2, true, false, false],
//            [$superUser2, $parent2, false, false, false],

            /* Superusers can see superusers' details
             */
            [$superUser2, $superUser1, true, true, true],
            [$superUser2, $superUser1, false, true, true],
            [$superUser2, $superUser1, true, false, true],
            [$superUser2, $superUser1, false, false, true],
            [$superUser2, $superUser2, true, true, true],
            [$superUser2, $superUser2, false, true, true],
            [$superUser2, $superUser2, true, false, true],
            [$superUser2, $superUser2, false, false, true],

            /* Staff can see superusers' details
             */
            [$superUser2, $staff1, true, true, true],
            [$superUser2, $staff1, false, true, true],
            [$superUser2, $staff1, true, false, true],
            [$superUser2, $staff1, false, false, true],
            [$superUser2, $staff2, true, true, true],
            [$superUser2, $staff2, false, true, true],
            [$superUser2, $staff2, true, false, true],
            [$superUser2, $staff2, false, false, true],

            /* Guests cannot see superuser's details
             */
//            [$superUser2, $guest1, true, true, false],
//            [$superUser2, $guest1, false, true, false],
//            [$superUser2, $guest1, true, false, false],
//            [$superUser2, $guest1, false, false, false],
//            [$superUser2, $guest2, true, true, false],
//            [$superUser2, $guest2, false, true, false],
//            [$superUser2, $guest2, true, false, false],
//            [$superUser2, $guest2, false, false, false],

            /* Students can see staff details
             */
            [$staff1, $student1, true, true, true],
            [$staff1, $student1, false, true, true],
            [$staff1, $student1, true, false, true],
            [$staff1, $student1, false, false, true],
            [$staff1, $student2, true, true, true],
            [$staff1, $student2, false, true, true],
            [$staff1, $student2, true, false, true],
            [$staff1, $student2, false, false, true],

            /* Parents can see staff details
             * if reportStaffAccess is true
             */
            [$staff1, $parent1, true, true, true],
//            [$staff1, $parent1, false, true, false],
            [$staff1, $parent1, true, false, true],
//            [$staff1, $parent1, false, false, false],
            [$staff1, $parent2, true, true, true],
//            [$staff1, $parent2, false, true, false],
            [$staff1, $parent2, true, false, true],
//            [$staff1, $parent2, false, false, false],

            /* Superusers can see staff details
             */
            [$staff1, $superUser1, true, true, true],
            [$staff1, $superUser1, false, true, true],
            [$staff1, $superUser1, true, false, true],
            [$staff1, $superUser1, false, false, true],
            [$staff1, $superUser2, true, true, true],
            [$staff1, $superUser2, false, true, true],
            [$staff1, $superUser2, true, false, true],
            [$staff1, $superUser2, false, false, true],

            /* Staff can see their own details
             */
            [$staff1, $staff1, true, true, true],
            [$staff1, $staff1, false, true, true],
            [$staff1, $staff1, true, false, true],
            [$staff1, $staff1, false, false, true],

            /* Staff can see staff details
             */
            [$staff1, $staff2, true, true, true],
            [$staff1, $staff2, false, true, true],
            [$staff1, $staff2, true, false, true],
            [$staff1, $staff2, false, false, true],

            /* Guests cannot see staff details
             */
            [$staff1, $guest1, true, true, false],
            [$staff1, $guest1, false, true, false],
            [$staff1, $guest1, true, false, false],
            [$staff1, $guest1, false, false, false],
            [$staff1, $guest2, true, true, false],
            [$staff1, $guest2, false, true, false],
            [$staff1, $guest2, true, false, false],
            [$staff1, $guest2, false, false, false],

            /* Students can see staff details
             */
            [$staff2, $student1, true, true, true],
            [$staff2, $student1, false, true, true],
            [$staff2, $student1, true, false, true],
            [$staff2, $student1, false, false, true],
            [$staff2, $student2, true, true, true],
            [$staff2, $student2, false, true, true],
            [$staff2, $student2, true, false, true],
            [$staff2, $student2, false, false, true],

            /* Parents can see staff details
             * if reportStaffAccess is true
             */
//            [$staff2, $parent1, true, true, true],
//            [$staff2, $parent1, false, true, false],
//            [$staff2, $parent1, true, false, true],
//            [$staff2, $parent1, false, false, false],
//            [$staff2, $parent2, true, true, true],
//            [$staff2, $parent2, false, true, false],
//            [$staff2, $parent2, true, false, true],
//            [$staff2, $parent2, false, false, false],

            /* Superusers can see staff details
             */
            [$staff2, $superUser1, true, true, true],
            [$staff2, $superUser1, false, true, true],
            [$staff2, $superUser1, true, false, true],
            [$staff2, $superUser1, false, false, true],
            [$staff2, $superUser2, true, true, true],
            [$staff2, $superUser2, false, true, true],
            [$staff2, $superUser2, true, false, true],
            [$staff2, $superUser2, false, false, true],

            /* Staff can see staff details
             */
            [$staff2, $staff1, true, true, true],
            [$staff2, $staff1, false, true, true],
            [$staff2, $staff1, true, false, true],
            [$staff2, $staff1, false, false, true],
            [$staff2, $staff2, true, true, true],
            [$staff2, $staff2, false, true, true],
            [$staff2, $staff2, true, false, true],
            [$staff2, $staff2, false, false, true],

            /* Guests cannot see staff details
             */
            [$staff2, $guest1, true, true, false],
            [$staff2, $guest1, false, true, false],
            [$staff2, $guest1, true, false, false],
            [$staff2, $guest1, false, false, false],
            [$staff2, $guest2, true, true, false],
            [$staff2, $guest2, false, true, false],
            [$staff2, $guest2, true, false, false],
            [$staff2, $guest2, false, false, false],

            /* Students cannot see guest details
             */
            [$guest1, $student1, true, true, false],
            [$guest1, $student1, false, true, false],
            [$guest1, $student1, true, false, false],
            [$guest1, $student1, false, false, false],
            [$guest1, $student2, true, true, false],
            [$guest1, $student2, false, true, false],
            [$guest1, $student2, true, false, false],
            [$guest1, $student2, false, false, false],

            /* Parents cannot see guest details
             */
            [$guest1, $parent1, true, true, false],
            [$guest1, $parent1, false, true, false],
            [$guest1, $parent1, true, false, false],
            [$guest1, $parent1, false, false, false],
            [$guest1, $parent2, true, true, false],
            [$guest1, $parent2, false, true, false],
            [$guest1, $parent2, true, false, false],
            [$guest1, $parent2, false, false, false],

            /* Superusers can see guest details
             */
//            [$guest1, $superUser1, true, true, true],
//            [$guest1, $superUser1, false, true, true],
//            [$guest1, $superUser1, true, false, true],
//            [$guest1, $superUser1, false, false, true],
//            [$guest1, $superUser2, true, true, true],
//            [$guest1, $superUser2, false, true, true],
//            [$guest1, $superUser2, true, false, true],
//            [$guest1, $superUser2, false, false, true],

            /* Staff cannot see guest details
             */
            [$guest1, $staff1, true, true, false],
            [$guest1, $staff1, false, true, false],
            [$guest1, $staff1, true, false, false],
            [$guest1, $staff1, false, false, false],
            [$guest1, $staff2, true, true, false],
            [$guest1, $staff2, false, true, false],
            [$guest1, $staff2, true, false, false],
            [$guest1, $staff2, false, false, false],

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

            /* Students cannot see guest details
             */
            [$guest2, $student1, true, true, false],
            [$guest2, $student1, false, true, false],
            [$guest2, $student1, true, false, false],
            [$guest2, $student1, false, false, false],
            [$guest2, $student2, true, true, false],
            [$guest2, $student2, false, true, false],
            [$guest2, $student2, true, false, false],
            [$guest2, $student2, false, false, false],

            /* Parents cannot see guest details
             */
            [$guest2, $parent1, true, true, false],
            [$guest2, $parent1, false, true, false],
            [$guest2, $parent1, true, false, false],
            [$guest2, $parent1, false, false, false],
            [$guest2, $parent2, true, true, false],
            [$guest2, $parent2, false, true, false],
            [$guest2, $parent2, true, false, false],
            [$guest2, $parent2, false, false, false],

            /* Superusers can see guest details
             */
//            [$guest2, $superUser1, true, true, true],
//            [$guest2, $superUser1, false, true, true],
//            [$guest2, $superUser1, true, false, true],
//            [$guest2, $superUser1, false, false, true],
//            [$guest2, $superUser2, true, true, true],
//            [$guest2, $superUser2, false, true, true],
//            [$guest2, $superUser2, true, false, true],
//            [$guest2, $superUser2, false, false, true],

            /* Staff cannot see guest details
             */
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
