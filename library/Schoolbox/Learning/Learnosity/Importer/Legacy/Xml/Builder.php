<?php

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

/**
 * Construct a simplified flat array of sections/questions/answers/etc.
 * that represents a legacy quiz, where the data came from an xml file
 */
class Builder
{
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
                    'typeid' => trim((string)$question->xpath('typeid')[0]),
                    'marks' => trim((string)$question->xpath('marks')[0]),
                    'config' => $this->decompileQuestionConfig($question),
                    'name' => trim((string)$question->xpath('name')[0]->xpath('text')[0]),
                    'questiontext' => trim((string)$question->xpath('penalty')[0]),
                    'penalty' => trim((string)$question->xpath('typeid')[0]),
                ];

                foreach ($question->xpath('answers')[0]->xpath('answer') as $answer) {
                    $answerRow = [];
                }
                new \Alaress_Probe($question, $questionRow);
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
}
