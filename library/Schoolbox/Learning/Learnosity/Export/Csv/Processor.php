<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Learnosity\QuizFetcher;
use Schoolbox\Learning\Quiz\Attempt\Mapper;

/**
 * Exports every attempt's actual responses to csv
 *
 * It takes ages to compile each question type into a quiz, but I made this for you:
 * fd6cf47f-2f37-4e69-9db4-b9d196a19c95
 */
class Processor
{
    /**
     * These always appear in the csv
     */
    private const MANDATORY_COLUMNS = [
        'External ID',
        'User ID',
        'Last Name',
        'First Name',
        'Start',
        'Finish',
    ];


    /**
     * String to indicate when an attempt was never finished
     */
    private const ATTEMPT_INCOMPLETE = 'Incomplete';


    /**
     * Constructor.
     *
     * @param Api $api
     * @param QuizFetcher $quizFetcher
     * @param Mapper $attemptMapper
     */
    public function __construct(
        private Api $api,
        private QuizFetcher $quizFetcher,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper
    ) {
    }


    /**
     * Make a csv containing each attempt's details, corresponding to the current quiz's questions.
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function export(QuizDomainObject $assessment): void
    {
        $sessions = [];

        /* Known questions in the current incarnation of $assessment
         *
         * Note we don't support variations between attempts: we use the current quiz questions
         * as a structure around which to build the csv
         */
        $structure = $this->buildStructure($assessment);

        /* Get a list of all the attempts that were made
         */
        $attempts = $this->getAttempts($assessment);

        /* Get the learnosity equivalent of our attempts
         */
        if ($attempts->count()) {
            $learnositySessions = $this->getLearnositySessions($attempts);

            /* Translate meanings and make more succinct each learnosity session,
             * using $structure as a lookup where needed for mcq etc.
             */
            if (\count($learnositySessions)) {
                $sessions = $this->compileHumanReadable($structure, $learnositySessions);
            }
        }

        /* Send it
         */
        $this->outputData($assessment, $structure, $attempts, $sessions);
    }


    /**
     * Build an ordered list of question references in the current incarnation of $assessment,
     * a learnosity quiz, as best we can. There's no sequencing in the api that reflects what
     * you see in their ui; there's only sorting by created or updated time which is useless.
     *
     * @param QuizDomainObject $assessment
     * @return array
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     * @throws \Schoolbox\Learning\Learnosity\ApiException
     */
    private function buildStructure(QuizDomainObject $assessment): array
    {
        /* Discover the current quiz question structure and build around that
         */
        $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $assessment->getLearnosityReference()
            ],
        ])['data'][0]['data']['items'] ?? [];

        /* Use item references to enforce order of appearance for questions
         */
        $itemRefs = [];

        foreach ($itemReferences as $itemReference) {
            /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
             * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
             */
            if (!is_array($itemReference)) {
                $itemRefs[] = $itemReference;
            } else {
                $itemRefs[] = $itemReference['reference'];
            }
        }

        /* We need this to determine the order of appearance of questions in each item
         */
        $items = $this->api->dataGetRequest('/itembank/items', [
            'references' => $itemRefs
        ])['data'] ?? [];

        /* Reindex items to enforce item order
         */
        $indexedItems = [];

        foreach ($items as $item) {
            $indexedItems[$item['reference']] = $item;
        }

        $structure = [];

        foreach ($itemRefs as $itemRef) {
            foreach ($indexedItems[$itemRef]['questions'] as $question) {
                $structure[$question['reference']] = $question['type'];
            }
        }

        /* Once you have the items, look for the questions inside them.
         * Note that an item potentially contains multiple questions.
         */
        $questions = $this->api->dataGetRequest('/itembank/questions', [
            'item_references' => $itemRefs,
        ])['data'] ?? [];

        /* Enforce question order
         *
         * Multiple Questions inside an item might appear in some arbitrary order
         * compared to what users saw on screen; there's no sequence column upon
         * which we can sort. There's only created and updated timestamps but this
         * is useless for recreating what you see on screen
         *
         * For now, group them logically as best we can (sry) by using multiple api
         * calls to infer how the ui might be built. This is because you can reuse
         * questions in various items in any other quiz; the only known order is
         * what you get out of the /itembank/items api call, for a given quiz.
         *
         * sigh
         */
        $factory = new Factory();

        foreach ($itemRefs as $itemRef) {
            /* $itemRef might be an Item containing a Feature, not a Question.
             * Legacy quiz sections were converted to Feature Items - such Items
             * will never contain a Question as part of our migration, and should
             * be skipped.
             */
            if (!isset($questions[$itemRef])) {
                continue;
            }

            foreach ($questions[$itemRef] as $question) {
                $structure[$question['reference']] = [
                    'type' => $question['data']['type'],
                    /* Not entirely sure how it's possible, but stimulus might not exist
                     * Null coalesce for safety, for now
                     */
                    'stimulus' => \Scrub::html2text($question['data']['stimulus'] ?? ''),
                    /* Cross reference values to human-readable equivalents
                     * (Only applies to some question types)
                     */
                    'data' => $factory->produce($question['data']['type'])?->findMeaning($question)
                ];
            }
        }

        return $structure;
    }


    /**
     * Get a list of attempts for $assessment
     *
     * @param QuizDomainObject $assessment
     * @return \Schoolbox\GenericCollection
     * @throws \RuntimeException
     */
    private function getAttempts(QuizDomainObject $assessment): \Schoolbox\GenericCollection
    {
        /* Get the quiz equivalent
         */
        $quizId = $this->quizFetcher->findByAssessmentId($assessment->getId())->getId();

        /* Get a list of all the attempts that were made
         *
         * Note that some attempts might not have a meaningful learnosity_reference: they could have been created
         * via Quiz Quickmark to contain a Submission State.
         */
        return $this->attemptMapper->findByColumn('assess_id', $quizId);
    }


    /**
     * Get the learnosity equivalent of our Attempts
     *
     * @param \Schoolbox\GenericCollection $attempts
     * @return array
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     * @throws \Schoolbox\Learning\Learnosity\ApiException
     */
    private function getLearnositySessions(\Schoolbox\GenericCollection $attempts): array
    {
        /* No attempts? Exit early; sending an empty array to learnosity
         * results in getting EVERYTHING back
         */
        if (!$attempts->count()) {
            return [];
        }

        /* Disregard non-meaningful learnosity_reference values which are possible to spawn
         * by using Quiz Quickmark, which creates attempts without a learnosity reference.
         */
        $learnosityReferences = [];

        foreach ($attempts as $attempt) {
            if (null !== $attempt->getLearnosityReference()) {
                $learnosityReferences[] = $attempt->getLearnosityReference();
            }
        }

        /* No learnosity references? No trabajo
         * But be careful managing empty payloads
         */
        if (!\count($learnosityReferences)) {
            return [];
        }

        /* Ask learnosity about the questions in this quiz
         *
         * Despite the docs saying we can request up to 1000 sessions at once https://help.learnosity.com/hc/en-us/articles/18356498582557-sessions-endpoints-Data-API
         * Learnosity will actually drop all the data for anything except the last 50 sessions you specify :(
         * As such, we have to make multiple requests to get all the data
         */
        $results = [];

        foreach (array_chunk($learnosityReferences, 50) as $chunk) {
            $resultsChunk = $this->api->dataGetRequest('/sessions/responses', [
                'session_id' => $chunk
            ])['data'] ?? [];
            $results = array_merge($results, $resultsChunk);
        }

        /* Index by learnosity reference, for a quick correlation
         */
        $learnositySessions = array_fill_keys($learnosityReferences, null);

        foreach ($results as $result) {
            $learnositySessions[$result['session_id']] = $result;
        }

        return $learnositySessions;
    }


    /**
     * Swap learnosity identifiers for human-readable equivalents
     * This approach seems like complete insanity to me, but whatever
     *
     * @param array $structure
     * @param array $learnositySessions
     * @return array
     */
    private function compileHumanReadable(array $structure, array $learnositySessions): array
    {
        $sessions = [];

        /* Note that if you complete a quiz then quickly load this screen you'll get a bunch
         * of "foreach() on null" issues; this is because learnosity is still transacting and
         * will only last a moment. By the time the average user gets here it should be ready
         */
        $factory = new Factory();

        foreach ($learnositySessions as $sessionReference => $learnositySession) {
            foreach ($learnositySession['responses'] as $response) {
                /* Only include an attempt's question response if it exists in the quiz's current
                 * set of non-deleted questions
                 */
                if (isset($structure[$response['question_reference']])) {
                    $sessions[$sessionReference][$response['question_reference']] = $factory->produce($response['question_type'])?->findHumanReadable($structure, $response);
                }
            }
        }

        return $sessions;
    }


    /**
     * Manufacture header row, whose columns consist of questions' stimuli
     *
     * @param array $structure
     * @param resource $output
     * @return void
     */
    private function buildHeaderRow(array $structure, $output): void
    {
        $row = self::MANDATORY_COLUMNS;

        /* Augment with questions' stimulus, replacing html with text equivalent
         */
        foreach ($structure as $questionReference => $data) {
            $row[] = \Scrub::html2text($data['stimulus']);
        }

        /* Add attempt marks and commentary as a backup
         */
        $row[] = 'Mark';
        $row[] = 'Normalised Mark';
        $row[] = 'Comment';
        $row[] = 'Reviewed At';

        fputcsv($output, $row, ',', '"');
    }


    /**
     * Build actual csv rows
     *
     * @param array $structure
     * @param GenericCollection $attempts
     * @param array $sessions
     * @param resource $output
     * @return void
     */
    private function buildDataRows(
        array $structure,
        \Schoolbox\GenericCollection $attempts,
        array $sessions,
        $output
    ): void {
        foreach ($attempts as $attempt) {
            $row = [
                $attempt->getUser()->getExternalId(),
                $attempt->getUser()->getId(),
                $attempt->getUser()->getLastname(),
                $attempt->getUser()->getFirstname(),
                $attempt->getStart()->getShort(),
                $attempt->getFinish() ? $attempt->getFinish()->getShort() : self::ATTEMPT_INCOMPLETE
            ];

            /* If this attempt was created via Quiz Quickmark, it won't have a session reference
             * (learnosity_reference) in learnosity itself: treat it differently
             */
            $sessionReference = $attempt->getLearnosityReference();
            $responseData = [];

            /* Load up whatever learnosity knows, if a sessionReference was meaningful
             * Otherwise, fill cells with emptiness from the abyss
             */
            if (null !== $sessionReference) {
                $responseData = $sessions[$sessionReference];
            }

            foreach ($structure as $itemReference => $data) {
                $row[] = $responseData[$itemReference] ?? '';
            }

            /* Attempt outcomes, as a backup
             * Only show published marks and commentary
             */
            if (null === $attempt->getReviewed()) {
                $row[] = '';
                $row[] = '';
                $row[] = '';
            } else {
                $row[] = $attempt->getMark();
                $row[] = $attempt->getNormalisedMark();
                $row[] = \Scrub::html2text($attempt->getComment() ?? '');
            }

            $row[] = $attempt->getReviewed()?->getShort();

            fputcsv($output, $row, ',', '"', escape: '');
        }
    }


    /**
     * Facade method for making a csv and headers etc.
     *
     * @param QuizDomainObject $assessment
     * @param array $structure
     * @param GenericCollection $attempts
     * @param array $sessions
     * @return void
     */
    private function outputData(
        QuizDomainObject $assessment,
        array $structure,
        \Schoolbox\GenericCollection $attempts,
        array $sessions
    ): void {
        /* Start generating output to stream
         */
        $filename = sprintf(
            'exported_responses_%s.csv',
            $assessment->getId()
        );

        header('Cache-Control: private');
        header('Content-type: text/ascii');
        header("Content-disposition: attachment; filename=\"{$filename}\"");

        $output = fopen('php://output', 'rw');

        /* Slam together a header row, but you need to compile the questions' text first
         */
        $this->buildHeaderRow($structure, $output);

        /* Start building rows
         */
        $this->buildDataRows($structure, $attempts, $sessions, $output);

        fclose($output);
    }
}
