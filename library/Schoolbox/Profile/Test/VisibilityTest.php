<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Profile\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Exception;
use Schoolbox\Profile\Visibility;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * This tests profile detail visibility to another user
 */
class VisibilityTest extends TestCase
{

    /** @var Visibility */
    private $visibility;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        try {
            \Database::getInstance()->connect(SQL_dsn, SQL_user, SQL_passwd, ['throw' => true]);
            $external = new \External_User();
        } catch (Exception $e) {
            $external = new \External_Offline_User();
        }

        parent::__construct($name, $data, $dataName);
        $config           = ConfigMapper::getInstance();
        $this->visibility = new Visibility($external, $config);
    }

    /**
     * @param User $viewer
     * @param User $viewee
     * @param bool $expected
     * @dataProvider provider_isVisible
     */
    public function test_isVisible(
        User $viewer,
        User $viewee,
        bool $expected
    ): void {
        $this->assertEquals($this->visibility->isDetailVisible($viewee, $viewer), $expected);
        if ($viewee->getRole()->isStudent() && $viewer->getRole()->isStudent()) {
            $this->assertEquals($this->visibility->isEmailVisible($viewee, $viewer), true);
        } else {
            $this->assertEquals($this->visibility->isEmailVisible($viewee, $viewer), $expected);
        }
    }

    /**
     * @return array
     */
    public function provider_isVisible(): array
    {
        $config              = ConfigMapper::getInstance();
        $restrictParentEmail = $config->get('restrict_parent_emails');
        $allowStaffAccess    = $config->get('report_staff_access');

        // viewer, viewee, visibility
        $studentRole = (new Role())->setType(Role::STUDENT);
        $staffRole   = (new Role())->setType(Role::STAFF);
        $parentRole  = (new Role())->setType(Role::PARENT);
        $guestRole   = (new Role())->setType(Role::GUEST);

        $superUser1 = (new User())->setId(1)->setFirstname('Super user 1')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff1     = (new User())->setId(2)->setFirstname('Test Staff 1')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student1   = (new User())->setId(3)->setFirstname('Test Student 1')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent1    = (new User())->setId(4)->setFirstname('Test Parent 1')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest1     = (new User())->setId(4)->setFirstname('Test Guest 1')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
        $superUser2 = (new User())->setId(1)->setFirstname('Super user 2')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
        $staff2     = (new User())->setId(2)->setFirstname('Test Staff 2')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
        $student2   = (new User())->setId(3)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
        $parent2    = (new User())->setId(4)->setFirstname('Test Parent 2')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
        $guest2     = (new User())->setId(4)->setFirstname('Test Guest 2')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');

        return [
            [$superUser1, $superUser2, true],
            [$superUser1, $staff2, true],
            [$superUser1, $student2, true],
            [$superUser1, $parent2, true],
            [$superUser1, $guest2, true],
            [$staff1, $superUser2, true],
            [$staff1, $staff2, true],
            [$staff1, $student2, true],
            [$staff1, $parent2, true],
            [$staff1, $guest2, false],
            [$student1, $superUser2, true],
            [$student1, $staff2, true],
            [$student1, $student2, true],
            [$student1, $parent2, false],
            [$student1, $guest2, false],
            [$parent1, $superUser2, $allowStaffAccess],
            [$parent1, $staff2, $allowStaffAccess],
            [$parent1, $student2, false],
            [$parent1, $parent2, !$restrictParentEmail],
            [$parent1, $guest2, false],
            [$guest1, $superUser2, false],
            [$guest1, $staff2, false],
            [$guest1, $student2, false],
            [$guest1, $parent2, false],
            [$guest1, $guest2, false],
        ];
    }
}
