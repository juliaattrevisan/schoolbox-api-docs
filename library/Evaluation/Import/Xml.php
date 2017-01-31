<?php
/**
 * Evaluation_Import_Xml
 *
 * Import entire Evaluation items including sections, questions and solutions
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
use \Schoolbox\Learning\Quiz\Model as Quiz;
use \Schoolbox\Learning\Quiz\Mapper as QuizMapper;

abstract class Evaluation_Import_Xml
{
    /**
     * @var QuizMapper
     */
    private $quizMapper;

    /** @var Evaluation_Main */
	protected $assessHandler;

	/** @var Evaluation_Section */
	protected $sectionHandler;

	/** @var Evaluation_Factory */
	protected $factory;

	/** @var SitePage */
	protected $page;

    /** @var int */
	protected $categoryId;

	/**
     * File contents sent from browser
     *
     * @var string
     */
	protected $data;

    /**
     * Newly assigned assess_id
     *
     * @var int
     */
	protected $assess_id;

	/**
	 * The title of the Evaluation being imported
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Constructor
	 *
	 * @param int $categoryId
	 * @param Evaluation_Main $assessHandler
	 * @param Evaluation_Section $sectionHandler
	 * @param Evaluation_Factory $factory
	 * @param string $data
	 * @param SitePage $page
	 */
	public function __construct($categoryId, $assessHandler, $sectionHandler, $factory, $data, $page) {
		$this->setCategoryId($categoryId);
		$this->setAssessHandler($assessHandler);
		$this->setSectionHandler($sectionHandler);
		$this->setFactory($factory);
		$this->setData($data);
		$this->setPage($page);
		$this->quizMapper = QuizMapper::getInstance();
	}

	/**
	 * Load data from source
	 *
	 * @return SimpleXMLElement
	 */
	private function loadData() {
		//
		// Try once to load this.
		// If that fails - decode then load.
		$use_errors = libxml_use_internal_errors(true);
		$result = simplexml_load_string($this->getData());
		if (!($result instanceof SimpleXMLElement)) {
			$result = simplexml_load_string(html_entity_decode($this->getData(), ENT_QUOTES, 'UTF-8'));
		}
		libxml_use_internal_errors($use_errors);
		return $result;
	}

	/**
	 * Import data
	 */
	public function import()
    {
		$xml = $this->loadData();

		$this->checkValidQuizType($xml);

		$assess_id = $this->createEvaluation($xml);

		if (!$assess_id) {
			error(112);
		}

		$this->setAssessId($assess_id);

		/* Process each section in the assess
		 */
		for ($i=0; $i < count($xml->section); $i++) {
			$currentSection = $xml->section[$i];

			$sectionData = array(
                'section_id' => '',
                'assess_id' => $assess_id,
                'sequence' => $i+1,
                'title' => $currentSection->title,
                'description' => trim((string) $currentSection->description->text) ? trim((string) $currentSection->description->text) : $currentSection->description,
                'random' => $currentSection->random,
                'pool' => $currentSection->pool,
            );
			$section_id = $this->getSectionHandler()->create($sectionData);

			/* Process each question in the section
			 */
			for ($j=0; $j < count($currentSection->question); $j++) {
				$currentQuestion = $currentSection->question[$j];

				$questionHandler = $this->getFactory()->createQuestionModel((string)$currentQuestion->typeid);
				$answerHandler = $this->getFactory()->createAnswerModel((string)$currentQuestion->typeid);

				/* Only json-encode if there are config elements
				 *
				 * When you cast the std obj to an array, you can possibly
				 * end up with an empty config if the tags are separated
				 */
                /** @var SimpleXMLElement $configs */
                $configs = $currentQuestion->configs->children();
                if($configs->count()){
                    $config = [];
                    foreach ($configs as $configSXE){
                        $config[(string)$configSXE->name] = (string)$configSXE->value;
                    }
                    $config = json_encode($config);
                } else {
                    $config = 'NULL';
                }

				$question_id = $questionHandler->create(array(
					'question_id' => '',
					'section_id' => $section_id,
					'sequence' => $j+1,
					'type_id' => $currentQuestion->typeid,
					'marks' => $currentQuestion->marks,
					'question' => trim((string) $currentQuestion->questiontext->text),
					'config' => $config,
				));

				if (method_exists($answerHandler, 'importMoodleAnswer')) {
				    /** @var SimpleXMLElement $answers */
				    $answers = $currentQuestion->answers;
					$answerHandler->importMoodleAnswer($answers, $question_id);
				}
			}
		}
		return $assess_id;
	}

    /**
     * Ensure not trying to load a quiz as survey or vice-versa
     *
     * @param SimpleXMLElement $xml
     */
    protected function checkValidQuizType(SimpleXMLElement $xml)
    {
        if($xml->availability != $this->getAvailability()){
            // Rename 'assess' as 'quiz' for UI
            $format   = $xml->availability == Quiz::AVAILABILITY_ASSESS ? 'quiz' : $xml->availability;
            $importAs = $this->getAvailability() == Quiz::AVAILABILITY_ASSESS ? 'quiz' : $this->getAvailability();
            error(null, "Cannot import {$format} as a {$importAs}");
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @return int
     */
	protected function getMaxAttempts(SimpleXMLElement $xml){
        // Try to get max attempts if it's there, otherwise fall back to
        // allowresit (not allow_resit).
        // if allowresit is true, or is not unspecified:
        // max_attempts = 0 (unlimited), otherwise 1.
        $max_attempts = (int)$xml->maxattempts;
        if (strlen($max_attempts) === 0) {
            $allowResit  = (string)$xml->allowresit;
            $max_attempts = ($allowResit || strlen($allowResit) === 0) ? 0 : 1;
        }
        return $max_attempts;
    }

    /**
     * Creates the evaluation (without sections, questions or solutions)
     *
     * @param SimpleXMLElement $xml
     * @return int                   The newly assigned assess_id
     */
	abstract protected function createEvaluation(SimpleXMLElement $xml);

    /**
     * Is this import controller for 'assess' or 'survey' ?
     *
     * @return string
     */
    abstract protected function getAvailability();

    /**
     * @return QuizMapper
     */
    protected function getQuizMapper()
    {
        return $this->quizMapper;
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return Alaress_Date_Format
     */
    protected function createDateFromString($dateString, $format = Alaress_Date_Format::DATABASE_FORMAT)
    {
	    if(empty($dateString)){
	        return new Alaress_Date_NullDate();
        }
        return new Alaress_Date_Format($dateString, $format);
    }

	/**
	 * Parses a rich text node
	 *
	 * @param SimpleXMLElement $xml
	 * @param string           $nodename
	 *
	 * @return string
	 */
	protected function parseRichTextNode(SimpleXMLElement $xml, $nodename)
	{
		// Value should just be a single node, but maybe it wasn't wrapped in
		// <![CDATA[ ]]> tags.
		$value = trim("{$xml->$nodename}");
		if (!$xml->$nodename) {
			foreach ($xml->xpath("/quiz/$nodename/*") as $desc_node) {
				$value .= $desc_node->asXML();
			};
		}
		return $value;
	}

	/* Getters/Setters
	 */

	/**
	 * Set section handler
	 *
	 * @param Evaluation_Section $sectionHandler
	 */
	protected function setSectionHandler($sectionHandler) {
		$this->sectionHandler = $sectionHandler;
	}

	/**
	 * Get section handler
	 *
	 * @return Evaluation_Section
	 */
	protected function getSectionHandler() {
		return $this->sectionHandler;
	}

	/**
	 * @param Evaluation_Factory $factory
	 */
	protected function setFactory($factory) {
		$this->factory = $factory;
	}

	/**
	 * @return Evaluation_Factory
	 */
	protected function getFactory() {
		return $this->factory;
	}

	/**
	 * @param SitePage $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * @return SitePage
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * @param Evaluation_Main $assessHandler
	 */
	protected function setAssessHandler($assessHandler) {
		$this->assessHandler = $assessHandler;
	}

	/**
	 * @return Evaluation_Main
	 */
	protected function getAssessHandler() {
		return $this->assessHandler;
	}

	/**
	 * @param string $data
	 */
	protected function setData($data) {
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	protected function getData() {
		return $this->data;
	}

	/**
	 * @param int $categoryId
	 */
	protected function setCategoryId($categoryId) {
		$this->categoryId = $categoryId;
	}

	/**
	 * @return int
	 */
	protected function getCategoryId() {
		return $this->categoryId;
	}

	/**
	 * @param int $assess_id
	 */
	protected function setAssessId($assess_id) {
		$this->assess_id = $assess_id;
	}

	/**
	 * @return int
	 */
	public function getAssessId() {
		return $this->assess_id;
	}

	/**
	 * @param string $title
	 */
	protected function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}
}
