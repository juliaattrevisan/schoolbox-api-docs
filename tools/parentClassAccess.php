#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Permission\Access\UserAccess as UserAccessModel;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Student + Parents go into the same folder (group) in schoolbox
 *
 * Some schools want to put students into a folder, and also put those students' parents into the same folder.
 *
 * The first arg of the csv is the destination folder.
 * The next n args are folder codes of students whose parents should be put into the destination folder.
 *
 * 48, FREA1 = the parents of students in FREA1 will be put into folder 48 (Archery)
 *
 * 48, FREA1, EN344 = the parents of students in FREA1 will be put into folder 48 (Archery).
 *                    the parents of students in EN344 will also be put into folder 48 (Archery)
 *
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    // the name of the CSV to import
    private const OPT_SHORT_FILE = 'f:';

    // to load students as well as parents into the group
    private const OPT_LONG_STUDENTS = 'students';

    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_FILE];
    }


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_STUDENTS,
        ];
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit;
        }

        if (!$this->getOpt(self::OPT_SHORT_FILE)) {
            $this->getLogger()->error('No import filename specified');
            exit;
        }

        if (!file_exists($this->getOpt(self::OPT_SHORT_FILE))) {
            $this->getLogger()->error('Specified import file not found');
            exit;
        }

        $userMapper = UserMapper::getInstance();
        $userAccessMapper = UserAccessMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();

        // suppress the route cache mutations
        $userAccessMapper->unregisterCallbacks();

        $loadStudents = $this->getOpt(self::OPT_LONG_STUDENTS);

        /** @var Folder\Model[] $folders */
        $folders = [];
        $classes = [];

        $handle = fopen($this->getOpt(self::OPT_SHORT_FILE), 'rb');

        if (!is_resource($handle)) {
            // Couldn't read file
            $this->getLogger()->error('Could not locate file to import.');
            exit;
        }

        $handle = (new \Alaress\Csv\ValidCSV($handle))->skipBom();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) > 1) {
                if (!isset($classes[$row[0]])) {
                    $classes[$row[0]] = [];
                }

                $n = \count($row);

                for ($i = 1; $i < $n; $i++) {
                    $code = $row[$i];
                    if (strlen(trim($code)) > 0) {
                        $classes[$row[0]][] = $row[$i];
                    }
                }
            }
        }

        fclose($handle);

        if (!$this->perform()) {
            $this->getLogger()->warning('Planning assignation of parents to their students\' folders.');
            $this->getLogger()->warning('Provide --perform to update DB.');
        } else {
            $this->getLogger()->warning('Performing assignation of parents to their students\' folders.');
        }

        // We only need to spawn these once
        $externalUser = new \External_User();
        $externalClass = new \External_Class();

        foreach ($classes as $folderId => $codes) {
            try {
                $currentFolder = $folderMapper->findById($folderId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $codeString = implode(',', $codes);
                $this->getLogger()->warning("Failed to find folder with id: {$folderId}. Skipping adding users from '{$codeString}' to folder id {$folderId}");
                continue;
            }

            $folders[$folderId] = $currentFolder;

            if ($loadStudents) {
                // Remove all students from specified classes
                $students = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::STUDENT, UserAccess::ACCESS_READ, false);
                $this->getLogger()->notice("Removing student access from '{$folders[$folderId]->getTitle()}'");

                if ($this->perform()) {
                    foreach ($students as $student) {
                        $userAccessMapper->deleteByColumnList([
                            'uid' => $student->getId(),
                            'sid' => $folderId
                        ]);
                    }
                }
            }

            // Remove all parents from specified classes
            $parents = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::PARENT, UserAccess::ACCESS_READ, false);
            $this->getLogger()->notice("Removing parent access from '{$folders[$folderId]->getTitle()}'");

            if ($this->perform()) {
                foreach ($parents as $parent) {
                    $userAccessMapper->deleteByColumnList([
                        'uid' => $parent->getId(),
                        'sid' => $folderId
                    ]);
                }
            }

            if ($currentFolder->getInherit()) {
                $this->getLogger()->error("'{$folders[$folderId]->getTitle()}' (id: {$folders[$folderId]->getId()}) inherits permissions, cannot create group members");
                continue;
            }

            // Add parents from class codes to the specified classes
            foreach ($codes as $code) {
                if (trim($code ?? '') === '') {
                    continue;
                }

                $this->getLogger()->notice("Adding parents of '{$code}' students to '{$folders[$folderId]->getTitle()}'");

                // Optionally install students
                if ($loadStudents) {
                    $students = $externalClass->getStudents($code);

                    if (is_array($students)) {
                        foreach ($students as $student) {
                            $this->loadUserAccess($userMapper, $userAccessMapper, $student['synergy_id'], Role::STUDENT, $folderId);
                        }
                    }
                }

                // Install parents
                $parents = $this->getParents($code, $externalClass, $externalUser);

                if (is_array($parents)) {
                    foreach ($parents as $parent) {
                        $this->loadUserAccess($userMapper, $userAccessMapper, $parent['StudentContactID'], Role::PARENT, $folderId);
                        $this->loadUserAccess($userMapper, $userAccessMapper, $parent['StudentContactSpouseID'], Role::PARENT, $folderId);
                    }
                }
            }
        }

        // Reload cache (if not dry run)
        if ($this->perform()) {
            /** @var WriteCache $cache */
            $cache = $this->getDi()->get('subscriptionCache');
            $cache->setLog($this->getLogger());

            try {
                $cache->cacheAllUsers(INSTANCE_ID);
            } catch (UnderflowException $e) {
                $this->getLogger()->notice($e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Prints a help message to STDOUT
     *
     * @return void
     */
    private function printHelp(): void
    {
        $optShortFile    = rtrim(self::OPT_SHORT_FILE, ':');
        $optLongStudents = self::OPT_LONG_STUDENTS;
        $optShortPerform = CliTool\Kernel::OPT_SHORT_PERFORM;
        $optLongPerform  = CliTool\Kernel::OPT_LONG_PERFORM;
        $optLongHelp  = self::OPT_LONG_HELP;
        echo <<<HELP

Synopsis:
  Assigns parents of students into the students' folders, thus bundling students and their parents together.
  You can specify any csv file that follows the structure described in classes.csv
  Optionally you can use parameter --students to also install students into specified folders

Usage:
  ./parentClassAccess.php -$optShortFile classes.csv [--$optLongStudents] [--$optLongHelp] [--$optLongPerform]

Options:
  -$optShortFile             Required. Path to CSV file
  -$optShortPerform, --$optLongPerform  Optional. Without this flag, tool will only simulate changes
  --$optLongStudents     Optional. If present, create group membership for student class members
  --$optLongHelp         Optional. Print this help message and exit

HELP;
    }


    /**
     * Retrieve all parents of students in the specified subject
     *
     * @param string|null             $subCode
     * @param External_ClassInterface $externalClass
     * @param External_UserInterface  $externalUser
     * @return array
     */
    private function getParents(?string $subCode, External_ClassInterface $externalClass, External_UserInterface $externalUser): array
    {
        $students = $externalClass->getStudents($subCode);

        $contacts = [];

        if (is_array($students)) {
            foreach ($students as $student) {
                $contactsCurrent = $externalUser->getStudentContactList([$student['synergy_id']]);

                foreach ($contactsCurrent as $contactCurrent) {
                    $contacts[$contactCurrent['StudentContactID']] = $contactCurrent;
                }
            }
        }

        return $contacts;
    }


    /**
     * @param UserMapper       $userMapper
     * @param UserAccessMapper $userAccessMapper
     * @param string|null      $externalId
     * @param string           $roleType
     * @param int              $folderId
     */
    private function loadUserAccess(UserMapper $userMapper, UserAccessMapper $userAccessMapper, ?string $externalId, string $roleType, int $folderId)
    {
        // This would typically happen for a non-existent spouse, just do nothing in this case
        if (!strlen($externalId)) {
            return;
        }

        try {
            // Get primary contact
            $user = $userMapper->findByExternalId($externalId, $roleType);

            if (!isset($usersAdded[$user->getId()])) {
                // Give student access to the class
                if ($this->perform()) {
                    $userAccess = $userAccessMapper->createModel();
                    $userAccess->setUser($user);
                    $userAccess->setServiceIdentifier($folderId);
                    $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                    $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                    $userAccessMapper->save($userAccess);
                }

                $this->getLogger()->notice(" Added:    {$roleType} '{$user->getFirstname()} {$user->getLastname()}'");
                $usersAdded[$user->getId()] = true;
            } else {
                $this->getLogger()->notice(" Skipping: {$roleType} '{$user->getFirstname()} {$user->getLastname()}' (Already Added To Folder)");
            }
        } catch (MapperException $e) {
            $this->getLogger()->error(" Error:    External {$roleType} user ID {$externalId} not found.");
        } catch (UnexpectedValueException $e) {
            $this->getLogger()->error(" Error:    {$e->getMessage()}");
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
