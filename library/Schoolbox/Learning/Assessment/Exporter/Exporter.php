<?php
/**
 * CSV exporter for Accelerus
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Csv\Writer AS CsvWriter;
use Schoolbox\Filter\BrokenFilterException;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Filter\Filters\Assessment\AssessmentType;
use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Schoolbox\Filter\Filters\Assessment\YearDue;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Resource\Folder;
use InvalidArgumentException;
use UnexpectedValueException;

class Exporter
{

    /**
     * @var AssessmentFilters\IdFinder
     */
    private $idFinder;

    /** @var Assessment\MapperProxy */
    private $assessmentDataSource;


    /**
     * Exporter constructor.
     *
     * @param AssessmentFilters\IdFinder $idFinder
     * @param Assessment\MapperProxy     $assessmentDataSource
     */
    public function __construct(AssessmentFilters\IdFinder $idFinder, Assessment\MapperProxy $assessmentDataSource)
    {
        $this->idFinder             = $idFinder;
        $this->assessmentDataSource = $assessmentDataSource;
    }


    /**
     * Generate a csv of assessment result data in a specific format for Accelerus importing
     *
     * @param resource $file
     * @return resource
     * @throws InvalidArgumentException If any filter keys not recognised
     * @throws BrokenFilterException If a injected filter is unable to be processed
     */
    public function generate($file)
    {
        $assessments = $this->findAssessments();

        $headings = [
            'student code',
            'class code',
            'assessment title',
            'assessment item code',
            'due date',
            'weight',
            'result',
            'raw mark',
            'comment',
        ];

        $data = [];

        foreach ($assessments as $assessment) {
            $assessmentTitle = $assessment->getTitle();
            $workType        = $assessment->getWorkType();
            $weight          = $assessment->getWeight();
            /** @var Folder\Code\Model $folderCode */
            $folderCode = $assessment->getBaseContext()->getFolder()->getCodes()->first();

            /*
             * Check if there are square brackets inside the submission box name, if so - pull the contents from
             * inside the square brackets and use as assessment code (original way this was built for DLSC).
             * If square brackets aren't found then use the assessment type (new way that will be standard).
             * Note: the square bracket check will be phased out when we have moved De La Salle's assessment codes
             * from inside the title to assessment types.
             */
            $openBracketPos  = strpos($assessmentTitle, '[');
            $closeBracketPos = strrpos($assessmentTitle, ']');
            if ($openBracketPos !== false && $closeBracketPos !== false) {
                $codeLength     = $closeBracketPos - $openBracketPos;
                $assessmentCode = substr($assessmentTitle, $openBracketPos + 1, $codeLength - 1);
            } else {
                $assessmentCode = $workType ? trim($workType->getName()) : '';
            }

            // Load participation
            $singleCollection  = new GenericCollection(get_class($assessment), [$assessment]);
            $assessment = $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($singleCollection)->first();

            foreach ($assessment->getAllParticipants() as $participant) {
                /** @var Participant $participant */
                $feedback = $participant->getAuthoritativeFeedback(true);

                if(!$feedback) {
                    continue;
                }

                $mark = $feedback->getHumanReadableMark();
                $percentage = $feedback->getNormalisedMark();
                $comment = $feedback->getComment();

                // If this is a total mark, only show the actual mark (without '/ [total]')
                //[$mark] = explode(' / ', $mark);

                $data[] = [
                    $headings[0] => $participant->getUser()->getExternalId(),
                    $headings[1] => $folderCode->getCode(),
                    $headings[2] => $assessmentTitle,
                    $headings[3] => $assessmentCode,
                    $headings[4] => $participant->getDueDate()->getDatabaseDate(),
                    $headings[5] => $weight,
                    $headings[6] => $mark,
                    $headings[7] => $percentage,
                    $headings[8] => $comment
                ];
            }
        }

        $writer = new CsvWriter($file, $data);
        $writer->write();

        return $file;
    }


    /**
     * Find all relevant assessments for the export report:
     * * DueWork + Project
     * * Not in a UnitSpec
     * * Not in a Project
     * * Due in current year
     *
     * @return GenericCollection|IAssessable[]
     * @throws InvalidArgumentException If any filter keys not recognised
     * @throws BrokenFilterException If a injected filter is unable to be processed
     * @throws UnexpectedValueException
     */
    private function findAssessments()
    {
        $currentYear = date('Y');
        $filters     = [
            AssessmentType\Definition::class           => [
                AssessmentType\Definition::KEY_DUE_WORK => AssessmentType\Definition::KEY_DUE_WORK,
                AssessmentType\Definition::KEY_QUIZ => AssessmentType\Definition::KEY_QUIZ,
                AssessmentType\Definition::KEY_PROJECT  => AssessmentType\Definition::KEY_PROJECT,
            ],
            NullableContext\UnitSpec\Definition::class => [],
            NullableContext\Project\Definition::class  => [],
            YearDue\Definition::class                  => [$currentYear => $currentYear],
        ];
        $ids           = $this->idFinder->findIdsByFilters($filters);
        $assessments   = $this->assessmentDataSource->findAllByIds($ids);

        // Hydrate any required folder codes
        $foldersRequiringCodeHydration = [];
        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();
            if ($folder->getCodes() === null) {
                $foldersRequiringCodeHydration[$folder->getId()] = $folder;
            }
        }
        if (count($foldersRequiringCodeHydration)) {
            $folderMapper = Folder\Code\Mapper::getInstance();
            $folderMapper->applyCodesToCategories(new GenericCollection(Folder\Model::class, $foldersRequiringCodeHydration));
        }

        // Filter out assessments that belong to folders without a folder code
        $classAssessments = $assessments->filter(function (IAssessable $assessment) {
            return $assessment->getBaseContext()->getFolder()->getCodes()->count() > 0;
        });


        // Order assessments by folder code
        $sortFunction = function (IAssessable $left, IAssessable $right) {
            /**
             * @var Folder\Code\Model $leftFolderCode
             * @var Folder\Code\Model $rightFolderCode
             */
            $leftFolderCode  = $left->getBaseContext()->getFolder()->getCodes()->first();
            $rightFolderCode = $right->getBaseContext()->getFolder()->getCodes()->first();
            return strcmp($leftFolderCode->getCode(), $rightFolderCode->getCode());
        };

        return $classAssessments->sortByCallback($sortFunction, true);
    }
}
