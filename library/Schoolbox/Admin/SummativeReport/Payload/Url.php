<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Psr\Http\Message\UriInterface;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Role;
use Schoolbox\User\Pronoun\Model;
use Schoolbox\UserBulkMapper;

/**
 * Generate a set of (filename, url[]) tuples
 */
class Url
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    public const FILENAME_MAX_LENGTH = 250;


    private Generator $routeGenerator;
    private UriInterface $baseUrl;
    private \Schoolbox\Assessment\Term\Mapper $termMapper;
    private \Schoolbox\RoleMapper $roleMapper;
    private \Schoolbox\UserBulkMapper $userBulkMapper;
    private \Schoolbox\UserMapper $userMapper;
    private \Schoolbox\External\User $externalUser;



    /**
     * Constructor.
     *
     * @param Generator                $routeGenerator
     * @param UriInterface             $baseUrl
     * @param Mapper                   $termMapper
     * @param \Schoolbox\RoleMapper    $roleMapper
     * @param UserBulkMapper           $userBulkMapper
     * @param \Schoolbox\UserMapper    $userMapper
     * @param \Schoolbox\External\User $externalUser
     */
    public function __construct(
        Generator $routeGenerator,
        UriInterface $baseUrl,
        \Schoolbox\Assessment\Term\Mapper $termMapper,
        \Schoolbox\RoleMapper $roleMapper,
        \Schoolbox\UserBulkMapper $userBulkMapper,
        \Schoolbox\UserMapper $userMapper,
        \Schoolbox\External\User $externalUser,
    ) {
        $this->routeGenerator = $routeGenerator;
        $this->baseUrl        = $baseUrl;
        $this->termMapper = $termMapper;
        $this->roleMapper = $roleMapper;
        $this->userBulkMapper = $userBulkMapper;
        $this->userMapper = $userMapper;
        $this->externalUser          = $externalUser;
    }


    /**
     * @param \Schoolbox\Admin\SummativeReport\Model $report
     * @return array
     * @throws \JsonException
     */
    public function generate(\Schoolbox\Admin\SummativeReport\Model $report): array
    {
        $optionsPayload = $report->decode();

        /* Base url for report generation
         */
        $urlBase = $this->baseUrl;

        /* create a tokeniser instance to assist with the custom filenames
         */
        $tokenReplacer = new \Schoolbox\TokenReplacer\Simple();

        /* Generate a list of student identifiers:
         * demographic students + additional students
         *
         * Anyone in the selected years (demographic students)
         * plus anyone in the selected campuses (demographic students)
         * plus the specific guys you chose (additional students)
         *
         * This is accumulated via the bulkModify search function, believe it or not
         */
        $demographicStudentsIds = [];
        $additionalStudentIds = $optionsPayload['students'];

        /* Year Levels and Campus student demographics
         *
         * Check the config to see what Role Type should have come out in the zip
         * This lets you prevent staff users from getting a pdf when you only wanted students
         */
        if (\count($optionsPayload['yearLevels']) || \count($optionsPayload['campuses'])) {
            $roleIds = $this->roleMapper->findByColumn('group_type', Role::STUDENT)->indexBy('id')->keys();

            $demographicStudents = $this->userBulkMapper->bulkModifySearch(
                empty($optionsPayload['campuses']) ? [ UserBulkMapper::ANY ] : $optionsPayload['campuses'],
                $roleIds,
                UserBulkMapper::ANY,
                empty($optionsPayload['yearLevels']) ? [ UserBulkMapper::ANY ] : $optionsPayload['yearLevels'],
                UserBulkMapper::ANY
            );

            $demographicStudentsIds = array_column($demographicStudents, 'id');
        }

        /* Combine the list of students and blow away any null/zero/falsey values
         */
        $studentIds = array_filter(array_unique(array_merge($demographicStudentsIds, $additionalStudentIds)));

        /* Fetch hydrated instances
         */
        $reportStudents = $this->userMapper->findByColumn('id', $studentIds);

        /* Start generating report paths and any associated warnings
         */
        $reportConfigs = [];
        $warnings = [];
        $filenames = [];

        $this->externalUser->loadParents($reportStudents);
        foreach ($reportStudents as $student) {
            if (isset($optionsPayload['viewAs']) && $optionsPayload['viewAs'] === Role::PARENT) {
                /* Student doesn't have parents, but we tried to make reports
                 * formatted specifically for parents; skip them
                 */
                if ($student->getParents()->count() === 0) {
                    $warnings[] = sprintf(
                        'Unable to generate report for student id %d (%s). No parents.',
                        $student->getId(),
                        $student->getExternalId()
                    );

                    continue;
                }
            }

            /* See if we can make a url for this student's report
             */
            try {
                $reportPath = $this->routeGenerator->generate('adminSummativeReport.getStudentReport', [
                    'reportId' => $report->getId(),
                    'report' => $report,
                    'studentId' => $student->getId(),
                    'student' => $student,
                ]);

                if ($reportPath) {
                    /* Pacify any weird or wonky data in people's names and external ids
                     * to prevent hacking the filesystem somehow
                     */

                    /**
                     * create the custom filename for this report
                     */
                    $filename = $tokenReplacer->replace(
                        $report->getFileName(),
                        [
                            '[firstname]'     => \Scrub::filename($student->getFirstname() ?: 'unknown'),
                            '[surname]'       => \Scrub::filename($student->getLastname() ?: 'unknown'),
                            '[externalId]'  => \Scrub::filename($student->getExternalId() ?: 'unknown'),
                            '[year]'  => \Scrub::filename($student->getYear()?->getName() ?: 'unknown'),
                            //yes getGivenName returns the firstname if the preferred name is not set. but it says it can also return null... I don't trust this. so null coalesce
                            '[preferredname]' => \Scrub::filename($student->getGivenName() ?: 'unknown'),
                            '[title]'         => \Scrub::filename($student->getTitle() ?: 'unknown'),
                            '[they]'          => \Scrub::filename($student->getPronoun()?->getSubjective() ?: Model::SUBJECTIVE_DEFAULT),
                            '[They]'          => \Scrub::filename(ucfirst($student->getPronoun()?->getSubjective() ?: Model::SUBJECTIVE_DEFAULT)),
                            '[them]'          => \Scrub::filename($student->getPronoun()?->getObjective() ?: Model::OBJECTIVE_DEFAULT),
                            '[Them]'          => \Scrub::filename(ucfirst($student->getPronoun()?->getObjective() ?: Model::OBJECTIVE_DEFAULT)),
                            '[theirs]'        => \Scrub::filename($student->getPronoun()?->getPossessive() ?: Model::POSSESSIVE_DEFAULT),
                            '[Theirs]'        => \Scrub::filename(ucfirst($student->getPronoun()?->getPossessive() ?: Model::POSSESSIVE_DEFAULT)),
                            '[their]'         => \Scrub::filename($student->getPronoun()?->getPossessiveAdjective() ?: Model::POSSESSIVE_ADJECTIVE_DEFAULT),
                            '[Their]'         => \Scrub::filename(ucfirst($student->getPronoun()?->getPossessiveAdjective() ?: Model::POSSESSIVE_ADJECTIVE_DEFAULT)),
                            '[themselves]'    => \Scrub::filename($student->getPronoun()?->getReflexive() ?: Model::REFLEXIVE_DEFAULT),
                        ]
                    );
                    //grab the filename excluding the folder paths
                    $filenameBits = pathinfo($filename);
                    $file = $filenameBits['filename'];

                    //if there is no basename, then the file can become the filename
                    if ('.' === $filenameBits['dirname']) {
                        //trim the filename to the max length 250 characters
                        $filename = mb_substr($file, 0, self::FILENAME_MAX_LENGTH, 'UTF-8');
                    } else {
                        //trim the filename to the max length and add that back to the filepath
                        $filename = sprintf(
                            "%s/%s",
                            $filenameBits['dirname'],
                            mb_substr($file, 0, self::FILENAME_MAX_LENGTH, 'UTF-8') //trim the filename to the max length 250 characters
                        );
                    }

                    $copy = 0;
                    //if the filename has already been used, then increment by 1
                    while (in_array($filename, $filenames) && $copy !== 100) {
                        //check if there is already a (\d) value at the end of the filename
                        if (preg_match("(\(\d+\)$)", $filename, $matches)) {
                            //grab that (\d) value...
                            preg_match('(\d+)', $matches[0], $values);

                            //read the int of that match, and increment by 1
                            $copy = (int)$values[0] + 1;

                            //use that as the new append file count
                            $filename = str_replace($matches[0], sprintf("(%d)", $copy), $filename);
                        } else {
                            //fist instance of a dupe file, so add (1)
                            $filename .= '(1)';
                        }
                    }
                    //log the filename
                    $filenames[] = $filename;

                    //if count became 100, then log it as an error
                    if (100 === $copy) {
                        $warnings[] = sprintf(
                            'Unable to generate report for student id %d (%s). Too similar to existing filename',
                            $student->getId(),
                            $student->getExternalId()
                        );
                        continue;
                    }

                    /* Need this to fetch the Govt. Student Number and slap it onto summative reports
                     * Your connector might not supply this, so could be entirely null for all we know
                     */
                    $govtStudentNumber = null;

                    if ('' !== trim((string)$student->getExternalId())) {
                        $externalUser = (new \External_User())->getStudent($student->getExternalId());
                        $govtStudentNumber = $externalUser['StudentGovernmentStudentNumber'] ?? null;
                    }

                    $footer = $tokenReplacer->replace(
                        $report->getFooter(),
                        [
                            '[firstname]'        => ($student->getFirstname() ?: ''),
                            '[surname]'          => ($student->getLastname() ?: ''),
                            '[externalId]'       => ($student->getExternalId() ?: ''),
                            '[year]'             => ($student->getYear()?->getName() ?: ''),
                            //yes getGivenName returns the firstname if the preferred name is not set. but it says it can also return null... I don't trust this. so null coalesce
                            '[preferredname]'    => ($student->getGivenName() ?: ''),
                            '[fullname]'         => ($student->getFullName() ?: ''),
                            '[title]'            => ($student->getTitle() ?: ''),
                            '[govstudentnumber]' => ($govtStudentNumber ?: ''),
                            '[reportTitle]'      => ($report->getTitle() ?: ''),
                        ]
                    );

                    /* Assemble paths/hierarchy in the zip
                     *
                     * The zip file contains the student's externalId as a folder
                     * and their report as a pdf inside that folder so you can see
                     * who owns which file
                     */
                    $reportConfig = [
                        'filename' => sprintf("%s.pdf", $filename),
                        'url' => (string)$urlBase->withPath($reportPath),
                        'footer' => $footer,
                    ];

                    $reportConfigs[] = $reportConfig;
                }
            } catch (RouteNotFound $e) {
                /* noop
                 *
                 * Couldn't find the part for this student's report;
                 * just ignore these missing reports
                 */
            }
        }

        /* Return a (warnings, reportConfigs) tuple
         */
        return [
            'warnings' => $warnings,
            'reportConfigs' => $reportConfigs
        ];
    }


    /**
     * Find the total date range spanned by the all selected Academic Periods
     *
     * @param array $termIds
     * @return array
     * @throws \InvalidArgumentException
     */
    public function calculateDateRange(array $termIds): array
    {
        $startDate = null;
        $endDate = null;

        /* No terms specified? You aren't supposed to be here
         * and I have no idea how you managed to get here
         * (maybe you have a broken payload though)
         */
        if (!\count($termIds)) {
            throw new \InvalidArgumentException('Academic periods are mandatory; none found');
        }

        /* Otherwise traverse the set
         *
         * Find the earliest date and find the latest date
         * and use this as the date range
         */
        $terms = $this->termMapper->findByColumn('id', $termIds);

        foreach ($terms as $term) {
            if (!$startDate || $term->getStart()->lt($startDate)) {
                $startDate = $term->getStart();
            }

            if (!$endDate || $term->getDisplayFinish()->gt($endDate)) {
                $endDate = $term->getDisplayFinish();
            }
        }

        return [
            'startDate' => $startDate->getDatabaseDate(),
            'endDate' => $endDate->getDatabaseDate()
        ];
    }
}
