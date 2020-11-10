<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Meriden\Pastoral\CommandLineTool;

use Alaress\Database\Connector\IConnector;
use Aura\Router\Router;
use Custom\Meriden\Pastoral\StudentInvolvement\Factory;
use Custom\Meriden\Pastoral\StudentInvolvement\Gateway;
use InvalidArgumentException;
use Schoolbox\Application\CliTool\PdfGenerator\App;
use Schoolbox\Application\CliTool\PdfGenerator\SourceBundle;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\Sync\Provisioner\UserMapper;
use Schoolbox\User;
use Zend\Uri\UriInterface;

/**
 * Command-line PDF generator for Meriden's eReports.
 */
class PdfGenerator extends App
{
    // student ID to generate the report for
    private const OPT_LONG_STUDENT_ID = 'student-id:';

    // Year levels to generate the report for
    private const OPT_LONG_YEAR_LEVEL = 'year-level:';
    private const DEFAULT_YEAR_LEVELS = [7, 8, 9,];

    // Calendar year to generate a report for
    private const OPT_LONG_REPORT_YEAR = 'report-year:';

    // role-type override: ensure that only particular users may see reports
    private const OPT_LONG_ROLE_TYPE = 'role-type:';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return array_merge(
            parent::getLongOpts(),
            [
                self::OPT_LONG_STUDENT_ID,
                self::OPT_LONG_YEAR_LEVEL,
                self::OPT_LONG_REPORT_YEAR,
                self::OPT_LONG_ROLE_TYPE,
            ]
        );
    }


    /**
     * @inheritdoc
     */
    protected function printHelp(): void
    {
        $file              = $this->getToolFilename();
        $green             = self::COLOUR_GREEN;
        $white             = self::COLOUR_WHITE;
        $yellow            = self::COLOUR_YELLOW;
        $defaultReportYear = $this->getDefaultReportYear();
        $roleTypes         = implode(', ', Role::getRoleTypes());
        $baseOptionHelp    = $this->generateHelpMessageForBaseOptions();
        $help              = "{$white}NAME:
    Meriden eReport PDF Batch Generator

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    This tool generates an archive of eReports in PDF format.

OPTIONS
$baseOptionHelp

    $yellow--student-id=ID$white
        Just generate the report for the student with this TASS ID.

    $yellow--year-level=YEAR_LEVEL$white
        Just generate reports for students in this year level for the report's
        year, e.g. '--year-level=7'.
        Defaults to generating reports for all year levels which may have an
        eReport (i.e. 7, 8 and 9).

    $yellow--report-year=REPORT_YEAR$white
        Generate the reports for the given year.
        Defaults to the current year ($defaultReportYear).

    $yellow--role-type=ROLE_TYPE$white
        Print eReports as they would appear to users with this role type.
        Must be one of [{$roleTypes}]

EXAMPLES:
 * Write log to terminal, output to archive.zip
      $file 1> archive.zip
      $file -o archive.zip
 * Append log to export.log, output to archive.zip
      $file 2>> export.log 1> archive.zip
      $file -l export.log -o archive.zip
";
        fwrite(STDOUT, $help);
    }


    /**
     * @inheritdoc
     */
    protected function generatePdfSources(UriInterface $baseUrl): GenericCollection
    {
        // Determine and validate the role type, if there is one
        $roleType = $this->getOpt(self::OPT_LONG_ROLE_TYPE);
        if (isset($roleType) && !in_array($roleType, Role::getRoleTypes(), true)) {
            throw new InvalidArgumentException(
                sprintf('If provided, --role-type option must be one of: %s', Role::getRoleTypes())
            );
        }


        // Determine the report year and year levels to run the report for.
        $reportYear = $this->getOptAsInt(self::OPT_LONG_REPORT_YEAR) ?? $this->getDefaultReportYear();
        $yearLevel  = $this->getOptAsInt(self::OPT_LONG_YEAR_LEVEL);
        if (isset($yearLevel)) {
            $yearLevels = [$yearLevel];
        } else {
            $yearLevels = self::DEFAULT_YEAR_LEVELS;
        }
        $this->getLogger()->notice(
            sprintf(
                'Generating PDF reports for students in year(s) %s in calendar year %d',
                implode(', ', $yearLevels),
                $reportYear
            )
        );


        // Once we have a report year and year level:
        // figure out which students were in that year level that year.
        // (Since this relies upon student year level: it won't work well for
        //  fetching reports for students who are no longer at school.
        //  Schoolbox doesn't store the dates in which a student was enrolled
        //  within a particular year level, so there's nothing I can do there.)
        $yearDelta = $this->getCurrentYear() - $reportYear;


        // By default: generate for years 7, 8 and 9
        $searchYearLevels = [];
        foreach ($yearLevels as $yearLevel) {
            $searchYearLevels[] = $yearLevel + $yearDelta;
        }


        // Build the URL generator
        /** @var Router $router */
        $router = $this->getDi()->get('router');
        /** @var IConnector $externalDb */
        $externalDb   = $this->getDi()->get('databaseExternal');
        $userMapper   = UserMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $rubricDataSource     = RubricDataSource::getInstance();
        $userAccessMapper     = UserAccessMapper::getInstance();
        $urlGenerator         = new UrlGenerator(
            $router,
            new Factory(
                new Gateway($externalDb),
                $userMapper,
                $folderMapper,
                $assessmentDataSource,
                $rubricDataSource,
                $userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN),
                $userAccessMapper
            )
        );


        // Build the list of students
        // TODO for 20.2 year will be in a join table, so update the query
        $userQuery = $userMapper->getPredicateBuilder()
                                ->in('year', $searchYearLevels);
        $studentId = $this->getOpt(self::OPT_LONG_STUDENT_ID);
        if (isset($studentId)) {
            $userQuery = $userQuery->equals('synergy_id', $studentId);
        }

        /** @var User[] $students */
        $students = $userMapper->findByPredicates(
            $userQuery,
            [
                'order' => [
                    'lastname'  => 'asc',
                    'firstname' => 'asc',
                ],
            ]
        );
        $this->getLogger()->notice(
            sprintf('Found %d student(s) to generate reports for', count($students))
        );


        // Build the source bundles, each will have a PDF generated for it
        $result = new GenericCollection(SourceBundle::class);
        foreach ($students as $student) {
            $result->add(
                new SourceBundle(
                    sprintf('%s.pdf', $student->getExternalId()),
                    $urlGenerator->generate($reportYear, $student, $roleType),
                    sprintf(
                        'Generating report for %s, %s (ID: %d, TASS ID: %s)',
                        $student->getLastname(),
                        $student->getFirstname(),
                        $student->getId(),
                        $student->getExternalId()

                    )
                )
            );
        }
        return $result;
    }


    /**
     * What is the default year that this report runs for?
     *
     * @return int
     */
    private function getDefaultReportYear(): int
    {
        return $this->getCurrentYear();
    }


    /**
     * What is the current year?
     *
     * @return int
     */
    private function getCurrentYear(): int
    {
        return (int)date('Y');
    }
}
