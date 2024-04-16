<?php

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

/**
 * Construct a simplified flat array of sections/questions/answers/etc.
 * that represents a legacy quiz, where the data came from an xml file
 */
class Builder
{
    /**
     * Constructor.
     *
     * @param Strategy\Factory $strategyFactory
     */
    public function __construct(private Strategy\Factory $strategyFactory) {
    }


    /**
     * Construct a simplified flat array of sections/questions/answers/etc.
     * that represents a legacy quiz, where the data came from an xml file
     *
     * @param \SimpleXMLElement $xml
     * @return array
     */
    public function build(\SimpleXMLElement $xml): array
    {
        /* Final stack
         */
        $quiz = [
            /* A convenience summation to detect quiz size, since
             * Learnosity has a payload limit this lets us choose
             * the mose appropriate import strategy to use
             */
            'numQuestions' => 0,
            '_sections' => [],
        ];

        /* Get its sections
         */
        foreach ($xml->xpath('section') as $section) {
            $sectionRow = [
                '_questions' => [],
                'title' => trim((string)$section->xpath('title')[0]),
                'description' => trim((string)$section->xpath('description')[0]),
                'random' => trim((string)$section->xpath('random')[0]),
                'pool' => trim((string)$section->xpath('pool')[0])
            ];

            /* Get each section's questions
             */
            foreach ($section->xpath('question') as $question) {
                $questionRow = [
                    '_answers' => [],
                    'type_id' => trim((string)$question->xpath('typeid')[0]),
                    'marks' => trim((string)$question->xpath('marks')[0]),
                    'config' => $this->decompileQuestionConfig($question),
                    'name' => trim((string)$question->xpath('name')[0]->xpath('text')[0]),
                    'questiontext' => trim((string)$question->xpath('penalty')[0]),
                    'penalty' => trim((string)$question->xpath('typeid')[0]),
                ];

                /* xpath->answers is a one-node set, so you should read this really as $answers[0]
                 */
                $struct = $this->decompileAnswer($questionRow, $question->xpath('answers')[0]);

                /* Ensure each question knows about its potential answwrs
                 */
                $questionRow['_answers'] = $struct['answers'];

                /* Merge any answer-specific configurations found deeper in the xml
                 */
                $questionRow['config'] = array_merge($questionRow['config'], $struct['config']);

                /* Add it to the section stack
                 */
                $sectionRow['_questions'][] = $questionRow;

                /* Remember how many questions we saw here
                 */
                $quiz['numQuestions']++;
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
     * @return mixed
     * @throws \RuntimeException
     */
    private function decompileAnswer(array $question, \SimpleXMLElement $answers): array
    {
        /* Quiz only uses types 2, 4 and 5
         * (6,8,9,10,17 are all Survey)
         */
        return $this->strategyFactory->produce((int)$question['type_id'])?->decompileAnswer($answers);
    }
}
