<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Cli\DetectCloze\BrokenFillgap;
use Schoolbox\Learning\Learnosity\Importer\InlineImageConverter;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Database\Strategy\Fillgap;

/**
 * Detect legacy quizzes containing cloze exercises.
 * These are known to have imported incorrectly at the start of 2024.
 */
class DetectClozeTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * Show candidate stack, mostly for devs.
     */
    private const OPT_LONG_STACK = 'stack';


//    /**
//     * Show the analysis of quizzes to find out how many are conclusive
//     * and how many aren't
//     */
//    private const OPT_LONG_ANALYSIS = 'analysis';


    /**
     * Show payloads that would potentially repair conclusively-broken cloze exercises
     * Use --perform to actually do the deed
     */
    private const OPT_LONG_REPAIR = 'repair';


    /**
     * --assessment
     *
     * Update a particular quiz from the schoolbox.assessment table
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';


    /**
     * @var Api
     */
    private Api $api;


    /**
     * @var string
     */
    private string $domain;


    /**
     * @var InlineImageConverter
     */
    private InlineImageConverter $inlineImageConverter;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_STACK,
//            self::OPT_LONG_ANALYSIS,
            self::OPT_LONG_REPAIR,
            self::OPT_LONG_ASSESSMENT_ID
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

Usage: detectcloze.php [--analyse] [--repair] [--perform] [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Detect Cloze.');
        $this->getLogger()->info('');

        /* Initialise dependencies
         * Note: we don't have access to $_SERVER superglobal here
         */
        $server = (new \Schoolbox\Url\Factory($this->getConfig()))->buildFullyQualified();
        $serverName = $server->getHost();
        $this->domain = (string)$server;

        $this->inlineImageConverter = new InlineImageConverter(\Schoolbox\Resource\Storage\Mapper::getInstance(), $this->getLogger());

        /* Find all the quizzes that contain a cloze exercise
         */
        $assessmentId = $this->getOpt(self::OPT_LONG_ASSESSMENT_ID);

        if ('' !== (string)$assessmentId) {
            $assessmentId = (int)$assessmentId;
        }

        /* Get some facts about candidates or, if specified, a particular assessment
         */
        $stack = $this->findCandidates($assessmentId);

        if ($this->getOpt(self::OPT_LONG_STACK)) {
            print_r($stack);
        }

//        /* Inspect the stack for things we can fix and things we can't
//         */
//        if ($this->getOpt(self::OPT_LONG_ANALYSIS)) {
//            print_r($this->analyse($stack));
//        }

        /* Inspect the stack for possible repair payloads
        */
        if ($this->getOpt(self::OPT_LONG_REPAIR)) {
            $this->repair($stack);
        }
    }


    /**
     * Find all the quizzes that contain cloze questions and keep a count
     * of how many times identical question text
     *
     * @param int|null $assessmentId
     * @return array
     * @throws \DOMException
     */
    private function findCandidates(?int $assessmentId): array {
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
                   'question_id %s has unparseable html - it might be truncated; skipping',
                   $row['question_id']
               ));

                continue;
            }

            /* Detect if multiple question stimuli functionally collide
             */
            $discriminator = sha1($question);

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
            if (!isset($stack[$assessId]['questions'][$discriminator])) {
                $stack[$assessId]['questions'][$discriminator] = [
                    'questionId' => [$questionId],
                    'question' => [
                        /* The original question's contents in Legacy Quiz
                         */
                        'original' => $question,
                        /* This is what we would have sent to learnosity itself during the mega migration
                         */
                        'predicted_learnosity_stimulus' => $this->produceQuestionText($question),
                    ],
                    'answer' => [
                        'broken' => (new BrokenFillgap($this->domain))->convert([
                            'question' => $question,
                            'marks' => $marks,
                            '_answers' => [
                                [
                                'answer' => $answer
                                ]
                            ]
                        ]),
                        /* This is a repair payload that we could potentially use
                         */
                        'repair' => (new Fillgap($this->domain))->convert([
                            'question' => $question,
                            'marks' => $marks,
                            '_answers' => [
                                [
                                    'answer' => $answer
                                ]
                            ]
                        ])
                    ],
                ];
            } else {
                /* Add it to the list of colliding questions
                 */
                $stack[$assessId]['questions'][$discriminator]['questionId'][] = $questionId;
            }
        }

        return $stack;
    }


//    /**
//     * Examine the candidate quizzes and see which ones can be repaired or not
//     *
//     * @param array $stack
//     * @return array|array[]
//     */
//    private function analyse(array $stack): array
//    {
//        /* A list of repairable quiz ids
//         */
//        $analysis = [
//            'repairable' => [
//                'ids' => [],
//                'count' => 0
//            ],
//            'inconclusive' => [
//                'ids' => [],
//                'count' => 0
//            ]
//        ];
//
//        /* Inspect for quizzes we can repair automatically
//         * and ones we can't...not sure what we do about them ones.
//         */
//        foreach ($stack as $assessId => $assessData) {
//            $hasCollisions = false;
//
//            foreach ($assessData['questions'] as $discriminator => $questionData) {
//                $collisions = \count($questionData['questionId']);
//
//                /* If this question stimulus was used more than once,
//                 * it's inconclusive w.r.t. repairing it programmatically
//                 */
//                if ($collisions > 1) {
//                    $this->getLogger()->info(sprintf(
//                        'Legacy quiz "%s" (assess_id: %s) has collisions in cloze exercise questions %s; cannot automatically repair.',
//                        $assessData['title'],
//                        $assessId,
//                        implode(', ', $questionData['questionId'])
//                    ));
//
//                    $hasCollisions = true;
//                }
//            }
//
//            /* A set of (assess id, learnosity reference) tuples for ones we can fix,
//             * and ones we can't.
//             */
//            $tuple = [
//                'title' => $assessData['title'],
//                'learnosity_reference' => $assessData['learnosity_reference'],
//            ];
//
//            if (!$hasCollisions) {
//                $analysis['repairable']['ids'][$assessId] = $tuple;
//                $analysis['repairable']['count']++;
//            } else {
//                $analysis['inconclusive']['ids'][$assessId] = $tuple;
//                $analysis['inconclusive']['count']++;
//            }
//        }
//
//        return $analysis;
//    }


    /**
     * Attempt an in-situ repair with suggested payload
     * (only works for conclusive cloze exercises)
     *
     * @param array $stack
     * @return void
     */
    private function repair(array $stack): void
    {
        foreach ($stack as $assessId => $assessData) {
            foreach ($assessData['questions'] as $discriminator => $questionData) {
                /* Only repair questions that could be conslusively repaired
                 * Ignore uncertainties
                 */
                if (1 !== \count($questionData['questionId'])) {
                    continue;
                }

                /* Perform Mode tells learnosity to get fixed up
                 * TODO actual update
                 */
                if ($this->perform()) {
                    $this->getLogger()->info('sending to learnosity');

                    /* Find the learnosity quiz for $assessData['learnosity_reference']
                     */
                    $this->getLogger()->debug(sprintf(
                        'Looking for learnosity reference %s',
                        $assessData['learnosity_reference']
                    ));

                    /* Iterate through all the items
                     *
                     * Find the Item tha contains a question whose text matches
                     * predicted_learnosity_stimulus
                     */
                    $this->getLogger()->debug(sprintf(
                        'Traversing...',
                    ));

                    /* Update it with the repair payload
                     */
                    $this->getLogger()->debug(sprintf(
                        'Performing update of question %s',
                        implode(', ', $questionData['questionId'])
                    ));
                } else {
                    print_r($questionData['answer']['repair']);
                }
            }
        }
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
