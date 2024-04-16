<?php

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use DebugBar\DebugBarException;

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
     * @throws DebugBarException
     */
    public function build(\SimpleXMLElement $xml): array
    {
        /* Final stack
         */
        $quiz = [
            '_sections' => []
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

                    $answerRow = $this->decompileAnswer($questionRow, $question->xpath('answers'));

//                    new \Alaress_Probe($question, $questionRow, $answerRow);


            }

        }
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
     * @param array $answers
     * @return mixed
     * @throws \RuntimeException
     */
    private function decompileAnswer(array $question, array $answers): array
    {
        /* Quiz only uses types 2, 4 and 5
         * (6,8,9,10,17 are all Survey)
         */
        return $this->strategyFactory->produce((int)$question['type_id'])?->decompileAnswer($answers);
    }
}
