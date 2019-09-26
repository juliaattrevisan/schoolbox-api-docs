#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Mapper;
use Schoolbox\Pastoral\Model;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{

    private const NUM_RECORDS = 2000;

    /** @inheritDoc */
    public function run(): void
    {
        // get teacher
        $roleMapper = new RoleMapper();
        $staffRoles = $roleMapper->findByColumn('group_type', 'staff');

        $userMapper = new UserMapper();
        $staffs     = $userMapper->findByColumn('group_id', $staffRoles->getPropertyValues('id'));

        $testStaffs = $userMapper->createCollection();
        $moderator  = null;
        foreach ($staffs as $staff) {
            if (!$moderator && $staff->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL)) {
                $moderator = $staff;
                continue;
            }
            $testStaffs->add($staff);
            if ($testStaffs->count() > 3) {
                break;
            }
        }

        // get students
        $studentRoles = $roleMapper->findByColumn('group_type', 'student');

        $students     = $userMapper->findByColumn('group_id', $studentRoles->getPropertyValues('id'));
        $testStudents = $userMapper->createCollection();
        foreach ($students as $student) {
            $student->loadParents();
            $testStudents->add($student);
            if ($testStudents->count() > 5) {
                break;
            }
        }

        // pastoral types
        $pastoralTypeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $types              = $pastoralTypeMapper->findAll();

        $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();

        // pastoral group
        $pastoralGroupMapper = \Schoolbox\Pastoral\Group\Mapper::getInstance();
        $pastoralGroup       = (new \Schoolbox\Pastoral\Group\Model())
            ->setName('Test ' . Alaress_Date_Format::now()->getDatabaseTime())
            ->setUser($moderator ?? $testStaffs->first());
        $pastoralGroupMapper->save($pastoralGroup);

        $memberMapper = \Schoolbox\Pastoral\Group\Member\Mapper::getInstance();
        $memberMapper->save((new \Schoolbox\Pastoral\Group\Member\Model())->setGroup($pastoralGroup)->setUser($testStaffs->current())->setAuthor($moderator ?? $testStaffs->current()));

        for ($i = 0; $i < $this::NUM_RECORDS; $i++) {
            /** @var \Schoolbox\User $ownerStaff */
            $ownerStaff = $testStaffs->offsetGet(rand(0, 2));
            $viewStaff  = $testStaffs->offsetGet(rand(0, 2));
            /** @var \Schoolbox\User $currentStudent */
            $currentStudent = $testStudents->offsetGet(rand(0, 4));
            $currentType    = $types->offsetGet(rand(1, $types->count()));
            $pastoral       = (new Model())->setHeading("Record {$i} {$ownerStaff->getFullName()}")
                                           ->setOwner($ownerStaff)
                                           ->setModifiedBy($ownerStaff)
                                           ->setStudent($currentStudent)
                                           ->setBodyPrivate("Private {$i}")
                                           ->setBodyPublic("Public {$i}")
                                           ->setType($currentType);
            $pastoralMapper = Mapper::getInstance();
            $pastoralMapper->save($pastoral);

            if ($i % 3 == 0) {
                $access = (new PastoralAccess())->setUser($currentStudent->getParents()->first())->setAccess(PastoralAccessMapper::ACCESS_PUBLIC)
                                                ->setPastoral($pastoral);
            } elseif ($i % 3 == 1) {
                $access = (new PastoralAccess())->setUser($viewStaff)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC)
                                                ->setPastoral($pastoral);
            } elseif ($i % 3 == 2) {
                $access = (new PastoralAccess())->setRole($staffRoles->current())->setAccess(PastoralAccessMapper::ACCESS_PUBLIC)
                                                ->setPastoral($pastoral);
            } else {
                $access = (new PastoralAccess())->setGroup($pastoralGroup)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC)
                                                ->setPastoral($pastoral);
            }

            // pastoral access
            $accessMapper->save($access);
        }
    }
};


$kernel = new CliTool\Kernel(
    $app,
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::CRITICAL,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
