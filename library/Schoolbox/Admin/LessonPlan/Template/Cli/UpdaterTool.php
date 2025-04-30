<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class UpdaterTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'lessonPlanTemplateUpdater';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 10;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * Where the csv files live
     */
    private const PATH = '/usr/share/schoolbox/tools/assessment/lessonPlanTemplate';


    /**
     * Renamed templates and their corresponding csv files containing Lesson Plan Template Fields
     */
    private const SOURCE_FILES = [
        '5E_Model_Lesson_Plan.csv' => [
            'old' => '5E Model',
            'new' => '5E Model Lesson Plan'
        ],
    ];


    /**
     * Some prepared statements
     *
     * @var \PDOStatement
     */
    private \PDOStatement $candidateTemplateStatement;
    private \PDOStatement $renameTemplateStatement;
    private \PDOStatement $createTemplateStatement;
    private \PDOStatement $templateFieldsStatement;
    private \PDOStatement $createFieldStatement;


    /**
     * Everything updated at this time
     */
    private string $now;


    /**
     * Corresponds to alaress admin username
     *
     * @var int
     */
    private int $superuserId;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Lesson Plan Template Updater

Updates lesson plan templates' titles, field descriptions and field bodies for the
schoolbox-created default data in admin. This matches based on titles, spawning new
templates and/or fields as required, deleting any that didn't match the spec contained
in the various csvs. 

Usage: php template-updater.php -vvv --perform


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        try {
            $this->getLogger()->info('Lesson Plan Template Updater.');
            $this->getLogger()->info('');

            /* Prepare some oft-used queries
             */
            $this->candidateTemplateStatement = $this->getDatabaseInternal()->prepare("
                SELECT *
                FROm lesson_plan_template
                WHERE title = :title
            ");

            $this->renameTemplateStatement = $this->getDatabaseInternal()->prepare("
                UPDATE lesson_plan_template
                SET title = :title
                WHERE id = :id
            ");

            $this->createTemplateStatement = $this->getDatabaseInternal()->prepare("
                INSERT INTO lesson_plan_template
                (title, enabled, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                VALUES (:title, :enabled, :createdAt, :createdBy, :updatedAt, :updatedBy, :deletedAt, :deletedBy)
            ");

            $this->templateFieldsStatement = $this->getDatabaseInternal()->prepare("
                SELECT *
                FROM lesson_plan_template_field
                WHERE lesson_plan_template_id = :templateId
                AND deleted_at IS NULL
                ORDER BY sequence
            ");

            $this->createFieldStatement = $this->getDatabaseInternal()->prepare("
                INSERT INTO lesson_plan_template_field
                (lesson_plan_template_id, title, body, description, visibility, sequence, created_at, updated_at, deleted_at)
                VALUES (:lessonPlanTemplateId, :title, :body, :description, :visiblity, :sequence, :createdAt, :updatedAt, :deletedAt)
            ");

            /* This is alaress admin
             */
            $this->superuserId = $this->findSuperuserId();

            /* I guess everything updated at this time
             */
            $this->now = \Alaress_Date_Format::now()->getDatabase();

            /* Each lesson plan template update comes in a csv file. Find all the lesson plan templates that match the
             * title specified in the csv, updating each of them synonymously.
             *
             * This includes lesson plan templates that the schools create if they match the title.
             *
             * Schools should be encouraged to rename their customised templates away, if they want to keep their
             * contents, before we invade their lesson plan templates.
             */
            foreach (self::SOURCE_FILES as $file => $titles) {
                /* Find all the templates that match the csv's title
                 */
                $templates = $this->findCandidateTemplates($titles);

                foreach ($templates as $template) {
                    /* Each template consists of multiple fields, which may or may not match the ones specified in the csv.
                     * Presume to remove everything *unless* it was referenced in the csv, thus handling deletions.
                     * This means that if a school created lesson plan template fields, we'll be removing them, forcing
                     * the template to match our csv spec.
                     */
                    if ($template['id']) {
                        $this->getLogger()->info(sprintf(
                            'Reusing existing lesson plan template "%s" (id: %s) and renaming it to %s',
                            $titles['old'],
                            $template['id'],
                            $titles['new']
                        ));

                        if ($this->perform()) {
                            $this->renameTemplateStatement->execute([
                                'id' => $template['id'],
                                'title' => $titles['new']
                            ]);
                        }

                        /* We'll need this later: fields are associated with a template
                         */
                        $templateId = (int)$template['id'];
                    } else {
                        /* Create a new one, I guess
                         */
                        $this->getLogger()->info(sprintf(
                            'Creating new lesson plan template "%s"',
                            $titles['new']
                        ));

                        if ($this->perform()) {
                            $this->createTemplateStatement->execute([
                                'title' => $template['title'],
                                'enabled' => $template['enabled'],
                                'createdAt' => $template['created_at'],
                                'createdBy' => $template['created_by'],
                                'updatedAt' => $template['updated_at'],
                                'updatedBy' => $template['updated_by'],
                                'deletedAt' => $template['deleted_at'],
                                'deletedBy' => $template['deleted_by']
                            ]);
                        }

                        /* We'll need this later: fields are associated with a template
                         */
                        $templateId = (int)$this->getDatabaseInternal()->lastInsertId();
                    }

                    /* Find all the existing fields for this lesson plan template and store them in a lookup, marking
                     * them as deletable for now until we change that via csv processing, thus handling deletions
                     */
                    $fields = $this->findFields($templateId);

                    foreach ($fields as &$field) {
                        $field['deleted_at'] = true;
                    }

                    unset($field);

                    /* Prepare a csv, loading its fields into a local lookup: we might have to use this more than once.
                     *
                     * Index the csvs by their title, for lack of any reliable identifier
                     * This might cause issues if template fields intentionally had the same title, but...that's weird
                     */
                    $updatedFields = $this->indexUpdatedFields($file);

                    /* Iterate through each csv's contents.
                     *
                     * If the field matches, update its description and body contents, then unset its delete flag
                     * because we want to keep this
                     *
                     * If it doesn't exist, spawn it
                     */
                    foreach ($updatedFields as $title => $updatedField) {
                        /* Apparently...this is required...for some reason
                         */
                        $index = (string)$title;

                        if (isset($fields[$index])) {
                            $fields[$index]['lesson_plan_template_id'] = $template['id'];
                            $fields[$index]['title'] = $updatedField[0];
                            $fields[$index]['description'] = $updatedField[1];
                            $fields[$index]['body'] = $updatedField[2];
                            $fields[$index]['visibility'] = (int)$updatedField[3] ? Role::STUDENT : Role::STAFF;
                            $fields[$index]['created_at'] = $this->now;
                            $fields[$index]['updated_at'] = $this->now;
                            $fields[$index]['deleted_at'] = null;

                            print_r($fields[$index]);
                        } else {
                            /* If it doesn't exist, spawn it
                             */
                        }
//
//                    /* Delete any field that was untouched by the update process.
//                     * This field no longer exists according to the csv.
//                     */
//                    foreach ($fields as $field) {
//                        if (null !== $field['deleted_at']) {
//                            $field->delete();
//                        }
                    }
                }
            }

            $this->getLogger()->info('');
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Find the first guy who used the alaress superuser username
     *
     * @return int|null
     */
    private function findSuperuserId(): ?int
    {
        /* This is alaress admin
         */
        $superuser = $this->getDatabaseInternal()->executeQuery("SELECT * FROM user WHERE username = :username LIMIT 1", [
            'username' => UserMapper::USERNAME_ALARESS_ADMIN
        ])->fetch();

        /* Can't find an alaress admin; we die
         */
        if (!$superuser) {
            $this->getLogger()->warning('Superuser not found; skipping Lesson Plan Template Updates.');

            return null;
        }

        return $superuser['id'];
    }


    /**
     * Index updated fields by their titles, for quick lookup
     * @param string $file
     * @return array
     */
    private function indexUpdatedFields(string $file): array
    {
        $fp = fopen(sprintf(
            '%s/%s',
            self::PATH,
            $file
        ), 'r');

        /* Prepare a csv, loading its fields into a local lookup: we might have to use this more than once.
         *
         * Index the csvs by their title, for lack of any reliable identifier
         * This might cause issues if template fields intentionally had the same title, but...that's weird
         */
        $updatedFields = [];

        while ($row = fgetcsv($fp)) {
            $updatedFields[$row[0]] = $row;
        }

        return $updatedFields;
    }


    /**
     * Find all the lesson plan templates that match $titles['old']
     * cos we're about to rename those ones
     *
     * @param array $titles
     * @return array
     */
    private function findCandidateTemplates(array $titles): array
    {
        /* Find all the templates that match the csv's title
         */
        $this->candidateTemplateStatement->execute([
            'title' => $titles['old']
        ]);

        $templates = $this->candidateTemplateStatement->fetchAll();

        /* If none exist, create one and shim it in with some default values
         */
        if (!\count($templates)) {
            $templates = [];

            $templates[] = [
                'id' => null,
                'title' => $titles['new'],
                'enabled' => 1,
                'created_at' => $this->now,
                'created_by' => $this->superuserId,
                'updated_at' => $this->now,
                'updated_by' => $this->superuserId,
                'deleted_at' => null,
                'deleted_by' => null,
            ];
        }

        return $templates;
    }


    /**
     * Find all the existing fields for this lesson plan template and store them in a lookup, marking
     * them as deletable for now until we change that via csv processing, thus handling deletions
     *
     * @param int $templateId
     * @return array
     */
    private function findFields(int $templateId): array
    {
        $this->templateFieldsStatement->execute([
            'templateId' => $templateId,
        ]);

        $fields = $this->templateFieldsStatement->fetchAll(\PDO::FETCH_ASSOC);

        $stack = [];

        foreach ($fields as $field) {
            $stack[$field['title']] = $field;
        }

        return $stack;
    }


    /**
     * Does not require special file permissions
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
