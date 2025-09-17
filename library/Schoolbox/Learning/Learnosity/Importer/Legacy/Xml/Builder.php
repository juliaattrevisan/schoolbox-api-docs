<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use Schoolbox\Learning\Learnosity\Importer\InlineImageConverter;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy\Factory;
use Schoolbox\Learning\Learnosity\Importer\RemoteInlineImageConverter;
use Schoolbox\User;

/**
 * Construct a simplified flat array of sections/questions/answers/etc.
 * that represents a legacy quiz, where the data came from an xml file
 */
class Builder
{
    /**
     * Our BlueprintBuilder uses section_id and question_id fields for its
     * logging; we don't have any such ids when we're manufacturing from
     * an xml file, so use this to maintain compatibility (it doesn't mean
     * anything in the xml context, anyways)
     */
    private const ID_PLACEHOLDER = 'derived from xml';


    /**
     * A list of errors (typically exceptions) that we encountered along the way
     * Normally these are nonfatal (such as reasons to skip a question) and don't
     * impede the rest of the import
     *
     * We can collate them here and report them in one big go at the end
     *
     * @var array
     */
    private array $errors = [];


    /**
     * Constructor.
     *
     * @param User $user
     * @param Factory $strategyFactory
     * @param RemoteInlineImageConverter $inlineImageConverter
     */
    public function __construct(
        private \Schoolbox\User $user,
        private Strategy\Factory $strategyFactory,
        private RemoteInlineImageConverter $inlineImageConverter,
    ) {
    }


    /**
     * Construct a simplified flat array of sections/questions/answers/etc.
     * that represents a legacy quiz, where the data came from an xml file
     *
     * @param array $instances
     * @param \SimpleXMLElement $xml
     * @return array
     * @throws \DOMException
     */
    public function build(array $instances, \SimpleXMLElement $xml): array
    {
        /* Augment final stack some facts about assessment instances
         */
        $quiz = [
            /* This user is assumed to be the author
             */
            'user_id' => $this->user->getId(),
            'assess_id' => $instances['quiz']->getId(),
            'assessment_id' => $instances['assessment']->getId(),
            'title' => $instances['assessment']->getTitle(),
            'duration' => $instances['assessment']->getDuration(),
            '_sections' => [],
        ];

        /* Get its sections
         */
        $nSection = 0;

        foreach ($xml->xpath('section') as $section) {
            $nSection++;

            $sectionRow = [
                'section_id' => self::ID_PLACEHOLDER,
                'title' => trim((string)$section->xpath('title')[0]),
                'description' => trim((string)$section->xpath('description')[0]),
                'random' => trim((string)$section->xpath('random')[0]),
                'pool' => trim((string)$section->xpath('pool')[0]),
                '_questions' => [],
            ];

            /* Get each section's questions
             */
            $nQuestion = 0;

            foreach ($section->xpath('question') as $question) {
                $nQuestion++;

                $questionRow = [
                    'question_id' => self::ID_PLACEHOLDER,
                    'type_id' => (int)trim((string)$question->xpath('typeid')[0]),
                    'marks' => trim((string)$question->xpath('marks')[0]),
                    'config' => $this->decompileQuestionConfig($question),
                    'name' => trim((string)$question->xpath('name')[0]->xpath('text')[0]),
                    'question' => '',
                    'penalty' => trim((string)$question->xpath('penalty')[0]),
                    '_answers' => [],
                ];

                /* Clean out any weird whitespace/control chars from the end of this,
                 * and convert base64 inline images to storage files
                 */
                try {
                    $questionRow['question'] = $this->inlineImageConverter->convert((string)$question->xpath('questiontext')[0]->xpath('text')[0]);
                } catch (\RuntimeException $e) {
                    /* This question has busted html
                     * Skip it quietly
                     */
                    $this->errors[] = sprintf(
                        'Section %s, question %s: %s',
                        $nSection,
                        $nQuestion,
                        $e->getMessage()
                    );

                    continue;
                }

                /* xpath->answers is a one-node set, so you should read this really as $answers[0]
                 */
                try {
                    $struct = $this->decompileAnswer($questionRow, $question->xpath('answers')[0]);
                } catch (\DomainException $e) {
                    $this->errors[] = sprintf(
                        'Section %s, question %s: %s ',
                        $nSection,
                        $nQuestion,
                        $e->getMessage()
                    );

                    continue;
                }

                /* Ensure each question knows about its potential answwrs
                 */
                $questionRow['_answers'] = $struct['answers'];

                /* Merge any answer-specific configurations found deeper in the xml
                 */
                $questionRow['config'] = array_merge($questionRow['config'], $struct['config']);

                /* Add it to the section stack
                 */
                $sectionRow['_questions'][] = $questionRow;
            }

            /* Add it to the quiz stack
             */
            $quiz['_sections'][] = $sectionRow;
        }

        return $quiz;
    }


    /**
     * Get a set of (key, value))
     *
     * @param \SimpleXMLElement $question
     * @return array
     */
    private function decompileQuestionConfig(\SimpleXMLElement $question): array
    {
        $configRow = [];

        foreach ($question->xpath('configs')[0]->xpath('config') as $config) {
            $key = trim((string)$config->xpath('name')[0]);
            $value = trim((string)$config->xpath('value')[0]);

            $configRow[$key] = $value;
        }

        return $configRow;
    }


    /**
     * Certain question types were supposed to have serialized answers
     * These were never really treated like a codec and the handlers
     * are intertwined with phplibs, which is annoying
     *
     * @param array $question
     * @param \SimpleXMLElement $answers
     * @return array
     * @throws \RuntimeException
     */
    private function decompileAnswer(array $question, \SimpleXMLElement $answers): array
    {
        /* Quiz only uses types 2, 4 and 5
         * (6,8,9,10,17 are all Survey)
         */
        $strategy = $this->strategyFactory->produce((int)$question['type_id']);

        if (null === $strategy) {
            throw new \DomainException(sprintf(
                'Unknown question type: %s',
                $question['type_id']
            ));
        }

        return $strategy->decompileAnswer($answers);
    }


    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
