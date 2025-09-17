<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Learnosity\Cli;

use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Learnosity\Cli\DetectCloze\BrokenFillgap;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Database\Strategy\Fillgap;
use Schoolbox\Learning\Learnosity\Importer\RemoteInlineImageConverter;

/**
 * Detect legacy quizzes containing cloze exercises.
 * These are known to have imported incorrectly at the start of 2024.
 */
class RepairClozeTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * --assessment
     *
     * Update a particular quiz from the schoolbox.assessment table
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';


    /**
     * --blueprint
     *
     * Show the payload we intend to send to learnosity land
     */
    private const OPT_LONG_BLUEPRINT = 'blueprint';


    /**
     * @var Api
     */
    private Api $api;


    /**
     * @var string
     */
    private string $domain;


    /**
     * @var RemoteInlineImageConverter
     */
    private RemoteInlineImageConverter $inlineImageConverter;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_BLUEPRINT
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find out how many legacy quizzes contain cloze exercise questions (fillgap)
because we mis-imported them at the start of 2024 during the mega migration.
These will have used the wrong regex to parse answers.

Using --blueprint will show you a dump of the data we accumulated and some
developer data structures are shown on the cli, allowing you to manually
inspect what we're finding. It won't try to do any repairs if you use this.

Using perform mode will actually execute mutations in learnosity, and it won't
show you the developer data structs. You should use this mode most of the time.

You can also use the --assessment option to limit this to only examining one
particular assessment at a time.

Note that repeatedly using this tool on the same data set will eventually result
in it telling you about "no matching questions found" - that's because you've
fixed them in learnosity and they can no longer be found in a broken state.

Usage: repaircloze.php [--blueprint] [--assessment=...] [--perform] [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Repair Cloze Exercises (Fillgap) from mega migration for 23.1');
        $this->getLogger()->info('');

        /* Initialise dependencies
         * Note: we don't have access to $_SERVER superglobal here
         */
        $server = (new \Schoolbox\Url\Factory($this->getConfig()))->buildFullyQualified();
        $serverName = $server->getHost();
        $this->domain = (string)$server;

        $this->api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        $this->inlineImageConverter = new RemoteInlineImageConverter($this->api, $this->getLogger());

        /* Find all the quizzes that contain a cloze exercise
         */
        $assessmentId = $this->getOpt(self::OPT_LONG_ASSESSMENT_ID);

        if ('' !== (string)$assessmentId) {
            $assessmentId = (int)$assessmentId;
        }

        /* Get some facts about candidates or, if specified, a particular assessment
         */
        $stack = $this->findCandidates($assessmentId);

        /* --blueprint option reveals candidate stack to stdout
         */
        if ($this->getOpt(self::OPT_LONG_BLUEPRINT)) {
            print_r($stack);

            exit();
        }

        /* Inspect the stack for possible repair payloads
         */
        $this->repair($stack);

        $this->getLogger()->info('');
        $this->getLogger()->info('Done.');
        $this->getLogger()->info('');
    }


    /**
     * Find all the quizzes that contain cloze questions and keep a count
     * of how many times identical question text
     *
     * @param int|null $assessmentId
     * @return array
     * @throws \DOMException
     */
    private function findCandidates(?int $assessmentId): array
    {
        $query = "
            SELECT
                assess.assess_id,
                assess.title,
                assess.learnosity_reference,
                assess.assessment_id,
                assess_section.section_id,
                assess_question.question_id,
                assess_question.question,
                assess_question.marks,
                assess_answer.answer
            FROM assess
            INNER JOIN assess_section ON assess.assess_id = assess_section.assess_id
            INNER JOIN assess_question on assess_question.section_id = assess_section.section_id
            INNER JOIN assess_answer ON assess_answer.question_id = assess_question.question_id
            WHERE assess.deleted_at IS NULL
            AND assess.availability = 'assess'
            AND assess_section.deleted_at IS NULL
            AND assess_question.deleted_at IS NULL
            AND assess_question.type_id = :typeId
        ";

        $params = [
            'typeId' => Fillgap::QUESTION_TYPE_ID
        ];

        /* Optionally filter to a particular assessment id
         */
        if ($assessmentId) {
            $query .= " AND assess.assessment_id = :assessmentId";

            $params['assessmentId'] = $assessmentId;
        }

        $statement = $this->getDatabaseInternal()->prepareQuery($query, $params);
        $statement->execute();

        /* Start compiling some info from legacy quiz data
         */
        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $assessId = $row['assess_id'];
            $title = $row['title'];
            $assessmentId = $row['assessment_id'];
            $learnosityReference = $row['learnosity_reference'];
            $questionId = $row['question_id'];
            $answer = $row['answer'];
            $marks = $row['marks'];

            try {
                $question = $this->inlineImageConverter->convert((string)$row['question']);
            } catch (\RuntimeException $e) {
                /* This question has busted html
                 * Skip it quietly
                 */
                $this->getLogger()->warning(sprintf(
                    'Preparation: question_id %s has unparseable html - it might be truncated; skipping',
                    $row['question_id']
                ));

                continue;
            }

            /* Group questions by their parent quiz
             */
            if (!isset($stack[$assessId])) {
                $stack[$assessId] = [
                    'title' => $title,
                    'assessment_id' => $assessmentId,
                    'learnosity_reference' => $learnosityReference,
                    'questions' => []
                ];
            }

            /* Emulate what we would have done during the mass migration:
             * Apply an inline image conversion for base64-embedded data and purify it.
             * That's what we would have sent to learnosity as the question's contents.
             */
            $brokenPayload = (new BrokenFillgap($this->domain))->convert([
                'question' => $question,
                'marks' => $marks,
                '_answers' => [
                    [
                        'answer' => $answer
                    ]
                ]
            ]);

            /* This is what it *should* have been
             */
            $repairPayload = (new Fillgap($this->domain))->convert([
                'question' => $question,
                'marks' => $marks,
                '_answers' => [
                    [
                        'answer' => $answer
                    ]
                ]
            ]);

            /* Stack them up if the $brokenPayload's answer is different to the $repairPayload's answer.
             * If they are already the same, there's nothing that would change. This happens for some simple
             * cases, but not for more complex ones.
             */
            if ($brokenPayload['validation']['valid_response']['value'] === $repairPayload['validation']['valid_response']['value']) {
                $this->getLogger()->info(sprintf(
                    'Preparation: Question %s would result in no changes; broken payload is the same as repair payload; skipping',
                    $questionId
                ));

                continue;
            }

            /* Otherwise, we'd actually make a difference trying to repair this question
             */
            $stack[$assessId]['questions'][$questionId] = [
                'question' => [
                    /* The original question's contents in Legacy Quiz
                     */
                    'original' => $question,
                    /* This is what we would have sent to learnosity itself during the mega migration
                     */
                    'predicted_learnosity_stimulus' => $this->produceQuestionText($question),
                ],
                'answer' => [
                    /* This is the broken answer we sent to learnosity
                     */
                    'broken' => $brokenPayload,
                    /* This is a repair payload that we could potentially use
                     */
                    'repair' => $repairPayload
                ],
            ];
        }

        return $stack;
    }


    /**
     * Attempt an in-situ repair with suggested payload
     * (only works for conclusive cloze exercises)
     *
     * @param array $stack
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     * @throws ApiException
     */
    private function repair(array $stack): void
    {
        /* Maintain a counter, for user experience
         */
        $i = 0;
        $numQuizzes = \count($stack);

        /* Perform Mode tells learnosity to get fixed up
         */
        foreach ($stack as $assessId => $assessData) {
            $i++;

            $this->getLogger()->info('');

            $this->getLogger()->info(sprintf(
                '(%s/%s) Processing "%s" (assess_id: %s, assessment_id: %s) with learnosity_reference %s',
                $i,
                $numQuizzes,
                $assessData['title'],
                $assessId,
                $assessData['assessment_id'],
                $assessData['learnosity_reference']
            ));

            /* Skip quizzes that have nothing to do
             */
            if (!\count($assessData['questions'])) {
                $this->getLogger()->info(sprintf(
                    '(%s/%s) No cloze exercises questions to fix; skipping',
                    $i,
                    $numQuizzes,
                ));

                continue;
            }

            /* Find the learnosity items used in $assessData['learnosity_reference']
             * Annoyingly you need the references, then fetch those separately
             *
             * In this case, a set of cloze exercises found in learnosity's data
             */
            $learnosityItems = $this->findItems(
                $this->findItemReferences($assessData['learnosity_reference']),
                (new BrokenFillgap($this->domain))->getLearnosityEquivalent()
            );

            /* Maintain a counter for questions
             */
            $j = 0;
            $numQuestions = \count($assessData['questions']);

            foreach ($assessData['questions'] as $questionId => $questionData) {
                $j++;
                $found = false;

                $this->getLogger()->debug(sprintf(
                    '(%s/%s) [%s/%s] Traversing learnosity quiz for something that matches this question...',
                    $i,
                    $numQuizzes,
                    $j,
                    $numQuestions
                ));

                /* Iterate through all the items
                 *
                 * Find the Item tha contains a question whose text matches predicted_learnosity_stimulus,
                 * and whose answer options match the broken answer ones we sent previously
                 */
                foreach ($learnosityItems as $learnosityItem) {
                    foreach ($learnosityItem['questions'] as $learnosityQuestion) {
                        /* Not a matching thing? Keep looking
                         */
                        if (!$this->matches($questionData, $learnosityQuestion)) {
                            continue;
                        }

                        /* At this point you should have a Question node that we can update and re-send back to
                         * Learnosity in situ; construct api call to do that
                         */
                        $this->getLogger()->info(sprintf(
                            '(%s/%s) [%s/%s] Found matching question in learnosity; updating question ref %s (question_id: %s)',
                            $i,
                            $numQuizzes,
                            $j,
                            $numQuestions,
                            $learnosityQuestion['data'][0]['reference'],
                            $questionId
                        ));

                        /* Update to use the correct answer template and options
                         */
                        $questionPayload = $learnosityQuestion['data'][0];
                        $questionPayload['data']['template'] = $questionData['answer']['repair']['template'];
                        $questionPayload['data']['validation']['valid_response']['value'] = $questionData['answer']['repair']['validation']['valid_response']['value'];

                        /* Perform update in learnosity
                         */
                        if ($this->perform()) {
                            $this->api->dataSetRequest('/itembank/questions', [
                                'questions' => [
                                    $questionPayload
                                ]
                            ]);
                        }

                        $this->getLogger()->debug(sprintf(
                            '(%s/%s) [%s/%s] Update request complete.',
                            $i,
                            $numQuizzes,
                            $j,
                            $numQuestions
                        ));

                        $found = true;
                    }
                }

                /* For some reason we never found a match; something in learnosit yhas changed
                 */
                if (!$found) {
                    $this->getLogger()->warning(sprintf(
                        '(%s/%s) [%s/%s] No learnosity equivalent found for question_id %s',
                        $i,
                        $numQuizzes,
                        $j,
                        $numQuestions,
                        $questionId
                    ));
                }
            }
        }
    }


    /**
     * Perform both checks to maximise chances of finding the correct question to repair:
     *     - Question stimulus text matches the one in learnosity
     *     - The broken answer template matches the one in learnosity
     *     - The broken answer options matches the ones in learnosity
     *
     * The fulfilment of these three criteria should be enough to indicate that this legacy question
     * matches the one in learnosity, and we can probably proceed with a blind repair
     *
     * It dodges around these user behaviours after the fact:
     *     - Fixed the question manually by changing it themselves
     *     - Reordering the question
     */
    private function matches(array $questionData, array $learnosityQuestion): bool
    {
        $ref = $learnosityQuestion['data'][0]['data'];

        /* The stimulus text must match
         */
        if ($questionData['question']['predicted_learnosity_stimulus'] === $ref['stimulus']) {
            /* The original answer template we built must also match
             */
            if ($questionData['answer']['broken']['template'] === $ref['template']) {
                /* The broken answers we would have sent during the mega migration
                 */
                $brokenAnswers = $questionData['answer']['broken']['validation']['valid_response']['value'];

                /* The answers learnosity knows about; a user may have changed these since mega migration
                 */
                $learnosityAnswers = $ref['validation']['valid_response']['value'];

                /* The broken answer contents must match what learnosity knows;
                 * Compare with learnosity and check correctness of both order and contents; must match exactly
                 */
                if ($brokenAnswers === $learnosityAnswers) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Find all the item references used by a particular quiz
     *
     * @param string $learnosityReference
     * @return array|null
     * @throws ValidationException
     * @throws \JsonException
     */
    private function findItemReferences(string $learnosityReference): ?array
    {
        $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $learnosityReference
            ]
        ])['data'][0]['data']['items'] ?? null;

        /* Can't find any items? Just leave
         */
        if (null === $itemReferences) {
            $this->getLogger()->info(sprintf(
                'No items found for learnosity_reference %s; skipping',
                $learnosityReference
            ));

            return null;
        }

        /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
         * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
         */
        if (is_array($itemReferences[0])) {
            /* Forcibly mutate this, because learnosity insanity
             */
            $itemReferenceList = array_column($itemReferences, 'id');
        } else {
            /* Presume it was a list of reference strings already
             */
            $itemReferenceList = $itemReferences;
        }

        return $itemReferenceList;
    }


    /**
     * Find actual item nodes from learnsity, coresponding to the iddentifiers in $itemReferenceList
     * We only return questions of type $questionType
     *
     * @param array  $itemReferenceList
     * @param string $questionType
     * @return array
     * @throws ValidationException
     * @throws \JsonException
     */
    private function findItems(array $itemReferenceList, string $questionType): array
    {
        $stack = [];

        $items = $this->api->dataGetRequest('/itembank/items', [
            'references' => $itemReferenceList
        ])['data'] ?? [];

        /* Filter the result to only clozetext question types
         * to save some mem and traversal
         */
        foreach ($items as $item) {
            foreach ($item['questions'] as &$question) {
                if ($questionType === $question['type']) {
                    /* Find more info about this particular question and shank it back into the item itself
                     */
                    $question = $this->api->dataGetRequest('/itembank/questions', [
                        'references' => [
                            $question['reference']
                        ]
                    ]);

                    $stack[] = $item;
                }
            }
        }

        return $stack;
    }


    /**
     * Prepares an arbitrary question richtext $question contents
     *    * negates attacks
     *    * replaces relative urls with absolute urls
     *
     * @param string $question
     * @return string
     */
    protected function produceQuestionText(string $question): string
    {
        return \Scrub::htmlAbsoluteUri($question, $this->domain);
    }
}
