#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Course\Import\ImportFactory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LessonPlanLinkedAssessmentsMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\LessonPlan\Activities\Replicator as LessonPlanActivitiesReplicator;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 */
$app = new class extends CliTool\App {
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    private const OPT_LONG_FILE = 'file:';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE,
        ];
    }


    /**
     * Output command-line help text.
     */
    private function outputHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        echo <<<HELP
$white
SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Bulk imports units to classes.

    $yellow-h, --help$white
        Print this help and exit.

    $yellow--file$white
        Filename to import.

    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, eg -vvv.

    $yellow-p$white
        Actually performs the import.

HELP;
    }


    /**
     * Run the cli tool.
     */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        // Help output
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->outputHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Perform/hard-delete messages
        if (!$this->perform()) {
            $this->getLogger()->info('Running in dry-run mode: run with the `-p` flag to perform changes');
        }

        $file = $this->getOpt(self::OPT_LONG_FILE);
        if (!file_exists($file)) {
            $this->getLogger()->error('Specified import file not found');
            exit;
        }

        $defaultDueTime = $this->getConfig()->get('lms_duetime_default');

        /** @var AssessmentDataSource $assessmentDataSource */
        // TODO does this need lesson plan replicator?
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $result               = (new \Schoolbox\Assessment\Course\Import\CsvImporter(
            FolderMapper::getInstance(),
            UserMapper::getInstance(),
            CourseImplementationMapper::getInstance(),
            UnitSpecificationMapper::getInstance(),
            new ImportFactory(
                $assessmentDataSource,
                new Replicator(
                    $assessmentDataSource,
                    QuizMapper::getInstance(),
                    RubricDataSource::getInstance(),
                    $this->getDi()->get('httpFactory'),
                    $this->getDi()->get('taskd.httpRequestMessageFactory'),
                    $this->getDi()->get('taskd.messageProducer'),
                    $this->getDi()->get('router.generator'),
                ),
                CourseImplementationMapper::getInstance(),
                UnitImplementationMapper::getInstance(),
                Component_InstanceMapper::getInstance(),
                Component_ConfigDefinitionMapper::getInstance(),
                Component_ConfigMapper::getInstance(),
            ),
            $assessmentDataSource,
        ))->import(fopen($file, 'r'), $defaultDueTime, $this->perform());

        $this->getLogger()->info(count($result) . ' combinations of unit & class ' . ($this->perform() ? 'are' : 'will be') . ' imported');
        foreach ($result as $unitImpl) {
            /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $unitImpl */
            $this->getLogger()->debug(sprintf("%s, %s", $unitImpl->getSpecification()->getTitle(), $unitImpl->getFolder()->getName()));
        }

        $this->deprecationNotice("SA-19334");
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO,]);
$kernel->runApp();
