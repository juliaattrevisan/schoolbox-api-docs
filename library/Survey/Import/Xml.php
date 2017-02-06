<?php
/**
 * Survey_Import_Xml
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
use Schoolbox\Learning\Quiz\Model as Quiz;
use \Evaluation\Import\Parser\IParser;
use \Survey\Import\Parser\IParser as SurveyParser;

/**
 * Survey-specific import logic
 */
class Survey_Import_Xml extends Evaluation_Import_Xml {

    /**
     * Gets a version-dependent parser that knows how to interpret a specific version format, but
     * respect a consistent API of representing the file's data.
     *
     * @param SimpleXMLElement $xml
     * @param string $fileFormatVersion
     * @return \Survey\Import\Parser\IParser|IParser
     */
    protected function getParser(SimpleXMLElement $xml, $fileFormatVersion)
    {
        switch($fileFormatVersion){
            case IParser::FORMAT_VERSION_160:
                return new \Survey\Import\Parser\V160($xml);
            case IParser::FORMAT_VERSION_165:
                return new \Survey\Import\Parser\V165($xml);
            default:
                throw new DomainException('No parser found for version: ' . $fileFormatVersion);
        }
    }

    /**
     * Creates the evaluation (without sections, questions or solutions)
     *
     * Returns the newly assigned 'assess_id'
     *
     * @param IParser|SurveyParser $parser
     * @return int
     */
    protected function createEvaluation(IParser $parser)
    {
        $dataArr = array(
            'category_id'        => $this->getCategoryId(),
            'user_id'            => $_SESSION['id'],
            'type'               => Evaluation_Main::ONLINE_ASSESSMENT_TYPE_ID,
            'title'              => $parser->getTitle(),
            'description'        => $parser->getDescription(),
            'completion_message' => $parser->getCompletionMessage(),
            'start'              => $parser->getStart(),
            'finish'             => $parser->getFinish(),
            'duration'           => Evaluation_Main::UNLIMITED_DURATION,
            'max_attempts'       => $parser->getMaxAttempts(),
            'availability'       => $this->getAvailability(),
            'anonymous_result'   => $parser->getAnonymousResult(),
            'notify_author'      => $parser->getNotifyAuthor(),
            'created_at'         => 'NOW()',
        );

        return $this->getAssessHandler()->create($dataArr);
    }

    /**
     * @inheritdoc
     */
    protected function getAvailability()
    {
        return Quiz::AVAILABILITY_SURVEY;
    }
}
