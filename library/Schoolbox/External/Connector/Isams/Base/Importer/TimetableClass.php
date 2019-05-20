<?php
/**
 * TimetableClass
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

class TimetableClass
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * TimetableClass constructor.
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Batchxml               $batchxml
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     * @param UserMapper             $userMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        UserMapper $userMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Get a list of (user_id, synergy_id) tuples
     *
     * @return array
     */
    private function findUserLookup(): array
    {
        $groupType = RoleMapper::ROLE_STUDENT;

        $userStatement = $this->userMapper->getDatabase()->query("
            SELECT u.`id`, synergy_id
            FROM {$this->userMapper->getTable()} u
            INNER JOIN `groups` g ON u.`group_id` = g.id
            WHERE synergy_id IS NOT NULL
            AND g.group_type = '{$groupType}'
        ");

        $users = [];

        foreach ($userStatement as $row) {
            $users[$row['synergy_id']] = $row['id'];
        }

        return $users;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        $this->classEntryMapper->getDatabase()->query("DELETE FROM {$this->classEntryMapper->getTable()}");
        $this->classInvolvementMapper->getDatabase()->query("DELETE FROM {$this->classInvolvementMapper->getTable()}");
        $this->classPeriodMapper->getDatabase()->query("DELETE FROM {$this->classPeriodMapper->getTable()}");

        /* Cache a flat key-value array of (user.id, user.synergy_id)
         * The alternative would be to iterate findById() in a loop which probably costs more at scale
         */
        $users = $this->findUserLookup();

        /* Find all the classes and their details as best we can
         *
         * Some parts will rely on data to have already been imported into local schema,
         * such as period ids, user ids, etc. so we try to get those and assemble them into
         * a convenient struct which we can then just ram home
         */
        try {
            $classes = new TimetableClassIterator($this->batchxml);
        } catch (\RuntimeException $e) {
            $this->log->critical('Cannot access api endpoint.');
            exit(1);
        } catch (InvalidUriPartException $e) {
            $this->log->critical('Cannot access api endpoint: bad url');
            exit(1);
        } catch (InvalidArgumentException $e) {
            $this->log->critical('Cannot access api endpoint: bad url');
            exit(1);
        }

        foreach ($classes as $class) {

            /* Process class entry
             */
            /** @var ClassEntry $timetableClass */
            $timetableClass = $this->classEntryMapper->createModel()
                ->setCode($class['code'])
                ->setName($class['name'])
                ->setDescription($class['name']);

            if ($this->perform) {
                $this->classEntryMapper->save($timetableClass);
            }

            $this->log->info(sprintf(
                'Creating class %s %s',
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));

            /* Process students
             */
            foreach ($class['students'] as $student) {

                /* If we have no record of this student id existing then we
                 * we can't assign them to any classes - they don't have a
                 * schoolbox user account
                 */
                if (isset($users[$student['id']])) {
                    $this->log->warning(sprintf(
                        'Tried to assign nonexistent schoobox account %s to class %s %s; skipping',
                        $student['id'],
                        $timetableClass->getName(),
                        $timetableClass->getCode()
                    ));

                    continue;
                }

                /* Minimum user
                 */
                $user = $this->userMapper->createModel()
                    ->setId($users[$student['id']]);

                /** @var ClassInvolvement $timetableClassInvolvement */
                $timetableClassInvolvement = $this->classInvolvementMapper->createModel()
                    ->setUser($user)
                    ->setClass($timetableClass);

                if ($this->perform) {
                    $this->classInvolvementMapper->save($timetableClassInvolvement);
                }

                $this->log->info(sprintf(
                    'Creating class involvement %s %s for user %s (user %s)',
                    $timetableClass->getName(),
                    $timetableClass->getCode(),
                    $student['preferredName'] . ' ' . $student['surname'],
                    $timetableClassInvolvement->getUser()->getId()
                ));

                /* TODO: Assign class (set) to period
                 * Currently isams doesn't have this information for some reason
                 */
            }
        }

        if (!$this->perform) {
            $this->log->info('Planning finished.');
        }
    }
}
