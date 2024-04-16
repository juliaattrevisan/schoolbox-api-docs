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

    }
}
