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

    /** @inheritDoc */
    public function run(): void
    {
        // get teacher
        $roleMapper = new RoleMapper();
        $roles      = $roleMapper->findByColumn('group_type', 'staff');

        $userMapper = new UserMapper();
        $users = $userMapper->findByColumn('group_id', $roles->getPropertyValues('id'));
        foreach ($users as $user) {
            if ($user->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL)) {
                $moderator = $user;
                continue;
            }
            if (!isset($firstTeacher)) {
                $firstTeacher = $user;
            } else {
                if (!isset($secondTeacher)) {
                    $secondTeacher = $user;
                } else {
                    break;
                }
            }
        }

        // get students
        $studentRoles = $roleMapper->findByColumn('group_type', 'student');

        $users        = $userMapper->findByColumn('group_id', $studentRoles->getPropertyValues('id'));
        $firstStudent = $users->first();

        // pastoral types
        $pastoralTypeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $types              = $pastoralTypeMapper->findAll();

        $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();

        // pastoral group
        $pastoralGroupMapper = \Schoolbox\Pastoral\Group\Mapper::getInstance();
        $pastoralGroup = (new \Schoolbox\Pastoral\Group\Model())
            ->setName('Test ' . Alaress_Date_Format::now()->getDatabaseTime())
            ->setUser($moderator ?? $firstTeacher);
        $pastoralGroupMapper->save($pastoralGroup);

        $memberMapper = \Schoolbox\Pastoral\Group\Member\Mapper::getInstance();
        $memberMapper->save((new \Schoolbox\Pastoral\Group\Member\Model())->setGroup($pastoralGroup)->setUser($secondTeacher)->setAuthor($moderator ?? $firstTeacher));

        for ($i = 0; $i < 200; $i++) {
            $pastoral       = (new Model())->setHeading("Record {$i}")
                                           ->setOwner($firstTeacher)
                                           ->setStudent($firstStudent)
                                           ->setBodyPrivate("Private {$i}")
                                           ->setBodyPublic("Public {$i}")
                                           ->setType($types->first());
            $pastoralMapper = Mapper::getInstance();
            $pastoralMapper->save($pastoral);

            if ($i % 3 == 0) {
                continue;
            }

            // pastoral access
//            $access = (new PastoralAccess())->setUser($secondTeacher)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE)
//                                            ->setPastoral($pastoral);
            $access = (new PastoralAccess())->setGroup($pastoralGroup)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE)
                                            ->setPastoral($pastoral);
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
