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
use Schoolbox\GenericCollection;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{

    private const NUM_RECORDS = 500;

    /** @var Schoolbox\Pastoral\Tag\Mapper */
    private $pastoralTagMapper;

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

        // pastoral tags
        $this->pastoralTagMapper = Schoolbox\Pastoral\Tag\Mapper::getInstance();
        $tags = $this->generateTags();

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

            $tagStart = rand(0, 2);
            $tagEnd = rand($tagStart, 5);
            for ($j = $tagStart; $j < $tagEnd; $j++) {
                $this->pastoralTagMapper->assignToRecord($pastoral, $tags->getOffset($j));
            }

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

            // add linked records
            $currentId = $pastoral->getId();

            try {
                // link a random existing record
                $link = $pastoralMapper->findById($currentId - rand(1, 4));
                // if it's a record to the same student or already linked, do not link
                if ($pastoral->getStudent()->getId() === $link->getStudent()->getId() || $link->getParent()) {
                    continue;
                }
                // if the random record is already linked to another record of the same student, don't link
                foreach ($link->getRelatedRecords() as $relatedRecord) {
                    if ($relatedRecord->getStudent()->getId() === $pastoral->getStudent()->getId()) {
                        continue 2;
                    }
                }
                // this random record is safe to link
                $pastoral->setParent($link);
                $pastoralMapper->save($pastoral);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop
            }
        }
    }

    /**
     * Generates pastoral tags
     * @return GenericCollection
     */
    private function generateTags(): GenericCollection
    {
        $tagNames = ['AAA', 'BBB', 'CCC', 'DDD', 'EEE'];
        $tags = $this->pastoralTagMapper->createCollection();
        foreach ($tagNames as $tagName) {
            /** @var Schoolbox\Pastoral\Tag\Model $tag */
            try {
                /* Assign an existing tag to this pastoral record
                 */
                $tag = $this->pastoralTagMapper->findOneByColumn('name', $tagName, ['cache' => false]);
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /* Restore this tag to active duty
                 */
                $tag = $e->getDeletedObject();

                $tag->setDeletedAt(null);
                $this->pastoralTagMapper->save($tag);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $tag = $this->pastoralTagMapper->createModel();
                $tag->setName($tagName);
            }
            $this->pastoralTagMapper->save($tag);
            $tags->add($tag);
        }
        return $tags;
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
