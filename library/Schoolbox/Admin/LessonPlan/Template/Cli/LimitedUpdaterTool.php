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

class LimitedUpdaterTool extends CliTool\App
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
     * Choose which datasets' templates
     */
    private const OPT_LONG_DATASET = 'dataset:';


    /**
     * Some plans only get changed/created in one set, but not the other
     */
    private const DATASET_INITIAL = 'initial';
    private const DATASET_DEMO = 'demo';


    /**
     * Renamed templates and their corresponding csv files containing Lesson Plan Template Fields
     */
    private const SOURCE_FILES = [
        '5E_Model_Lesson_Plan.csv' => [
            'old' => '5E Model',
            'new' => '5E Model Lesson Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
        'DP_Lesson_Plan.csv' => [
            'old' => 'DP Lesson PLan',  // YES I KNOW. This has a capital L in "Plan". It's like that in demo data.
            'new' => 'DP Lesson Plan',   // YES I KNOW. This seems to retain the same name as it originally had.
            'datasets' => [
                self::DATASET_DEMO,
            ],
        ],
// This USED to exist...not sure if it is supposed to anymore?
//        'Daily_Plan.csv' => [
//            'old' => 'Daily Planner',   // I presume Daily Plan became Daily Planner in the original spreadsheet
//            'new' => 'Daily Plan',      // I presume this meant to have a capital P in Plan
//            'datasets' => [],
//        ],
        'ELC_Learning_Plan.csv' => [
            'old' => 'ELC Learning Plans',  // This was lost in the spreadsheet, but this was its original name
            'new' => 'ELC Learning Plan',   // Everything else is singular so...I guess this is too?
            'datasets' => [
                self::DATASET_DEMO,
            ],
        ],
        'Generic_Primary_Lesson_Plan.csv' => [
            'old' => 'Generic Primary',
            'new' => 'Generic Primary Lesson Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
        'Generic_Secondary_Lesson_Plan.csv' => [
            'old' => 'Generic Secondary',
            'new' => 'Generic Secondary Lesson Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
        'Gradual_Release_Model_Lesson_Plan.csv' => [
            'old' => 'Gradual Release Model',
            'new' => 'Gradual Release Model Lesson Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
        'Hook_Teach_Task_Lesson_Plan.csv' => [
            'old' => 'Hook/Teach/Task',
            'new' => 'Hook/Teach/Task Lesson Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
        'IGCSE_Lesson_Plan.csv' => [
            'old' => 'IGCSE Lesson Plan',
            'new' => 'IGCSE Lesson Plan',
            'datasets' => [
                self::DATASET_DEMO,
            ],
        ],
        'Inquiry_Lesson_Plan.csv' => [
            'old' => 'Inquiry Lesson',
            'new' => 'Inquiry Lesson Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
        'PYP_Lesson_Plan.csv' => [
            'old' => 'PYP Lesson Plan',
            'new' => 'PYP Lesson Plan',
            'datasets' => [
                self::DATASET_DEMO,
            ],
        ],
        'Simplified_Lesson_Plan.csv' => [
            'old' => 'Simplified Lesson Plan',
            'new' => 'Simplified Lesson Plan',
            'datasets' => [
                self::DATASET_DEMO,
            ],
        ],
        // This is a completely new lesson plan template we've never seen before.
        'Detailed_Weekly_Plan.csv' => [
            'old' => 'Detailed Weekly Plan',
            'new' => 'Detailed Weekly Plan',
            'datasets' => [
                self::DATASET_DEMO,
            ],
        ],
        // This is a completely new lesson plan template we've never seen before.
        'Weekly_Plan.csv' => [
            'old' => 'Weekly Plan',
            'new' => 'Weekly Plan',
            'datasets' => [
                self::DATASET_INITIAL,
                self::DATASET_DEMO,
            ],
        ],
    ];


    /**
     * Some prepared statements
     *
     * @var \PDOStatement
     */
    private \PDOStatement $candidateTemplateStatement;
    private \PDOStatement $templateFieldsStatement;
    private \PDOStatement $updateFieldStatement;


    /**
     * Everything updated at this time
     */
    private string $now;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_DATASET,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Lesson Plan Template Updater (Limited)

Updates lesson plan templates' titles, field descriptions and field bodies for the schoolbox-created
default data in admin. This matches based on titles: lesson plan template titles and lesson plan 
template field titles, updating the fields' Field Descriptions to match those contained the csvs.
It only changes Field Descriptions if both the template title and field title matches exactly.

It won't create new templates or new fields. It won't delete existing templates or fields. 

Usage: php template-updater.php --dataset=[initial,demo] -vvv --perform

Ex.    php template-updater.php --dataset=initial -vvv --perform
Ex.    php template-updater.php --dataset=demo -vvv --perform

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
            $this->getLogger()->info('Lesson Plan Template Updater (Limited).');
            $this->getLogger()->info('');

            /* Prepare some oft-used queries
             */
            $this->prepare();

            /* I guess everything updated at this time
             */
            $this->now = \Alaress_Date_Format::now()->getDatabase();

            /* Check that this matches the dataset you chose to import
             */
            $dataset = trim((string)$this->getOpt(self::OPT_LONG_DATASET));

            /* This is mandatory
             */
            if ('' === $dataset) {
                $this->getLogger()->warning('Choose a dataset to update. See --help for more options.');
                return;
            }

            /* Each lesson plan template update comes in a csv file. Find all the lesson plan templates that match the
             * title specified in the csv, updating each of them synonymously.
             *
             * This includes lesson plan templates that the schools create if they match the title.
             *
             * Schools should be encouraged to rename their customised templates away, if they want to keep their
             * contents, before we invade their lesson plan templates.
             */
            foreach (self::SOURCE_FILES as $file => $titles) {
                $this->refreshLock($lock);

                /* Check that this matches the dataset you chose to import
                 */
                if (!\in_array($dataset, $titles['datasets'], true)) {
                    $this->getLogger()->debug(sprintf(
                        'Template "%s" not in the chosen dataset "%s": skipping',
                        $titles['new'],
                        $dataset
                    ));

                    continue;
                }

                /* Find all the templates that match the csv's title
                 */
                $templates = $this->findCandidateTemplates($titles);

                foreach ($templates as $template) {
                    $this->refreshLock($lock);

                    /* Each template consists of multiple fields, which may or may not match the ones specified in the csv.
                     */
                    $templateId = (int)$template['id'];

                    /* Find all the existing fields for this lesson plan template and store them in a lookup
                     * The field titles must match exactly for an update to potentially occur.
                     */
                    $fields = $this->findFields($templateId);

                    /* Prepare a csv, loading its fields into a local lookup: we might have to use this more than once.
                     *
                     * Index the csvs by their title, for lack of any reliable identifier
                     * This might cause issues if template fields intentionally had the same title, but...that's weird
                     */
                    $updatedFields = $this->indexUpdatedFields($file);

                    foreach ($updatedFields as $title => $updatedField) {
                        $this->refreshLock($lock);

                        /* Apparently...this is required...for some reason
                         */
                        $index = (string)$title;

                        /* We only update fields that exist: we never delete them, nor do we spawn new fields;
                         * also, we only update field descriptions. Their titles, bodies etc. all remain the same.
                         */
                        if (isset($fields[$index])) {
                            $struct = $fields[$index];

                            $this->getLogger()->debug(sprintf(
                                'Updating existing field "%s" (id: %s) in template "%s" (id: %s)',
                                $struct['title'],
                                $struct['id'],
                                $template['title'],
                                $templateId
                            ));

                            if ($this->perform()) {
                                $this->updateFieldStatement->execute([
                                    'id' => $struct['id'],
                                    'lessonPlanTemplateId' => $templateId,
                                    'description' => $updatedField[1],
                                    'createdAt' => $this->now,
                                    'updatedAt' => $this->now,
                                ]);
                            }
                        }
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
     * Set up prepared statements etc.
     *
     * @return void
     */
    private function prepare(): void
    {
        $this->candidateTemplateStatement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM lesson_plan_template
            WHERE title = :title
            AND deleted_at IS NULL
        ");

        $this->templateFieldsStatement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM lesson_plan_template_field
            WHERE lesson_plan_template_id = :templateId
            AND deleted_at IS NULL
            ORDER BY sequence
        ");

        $this->updateFieldStatement = $this->getDatabaseInternal()->prepare("
            UPDATE lesson_plan_template_field
            SET lesson_plan_template_id = :lessonPlanTemplateId,
                description = :description,
                created_at = :createdAt,
                updated_at = :updatedAt
            WHERE id = :id
        ");
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
            __DIR__,
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

        /* We no longer create new templates if they didn't already exist.
         * We only modify templates if they exist exactly as they used to exist.
         */
        return $this->candidateTemplateStatement->fetchAll();
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
