<?php
/**
 * Evaluation_Import_Xml
 *
 * Import entire Evaluation items including sections, questions and solutions
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
use \Schoolbox\Learning\Quiz\Model as Quiz;
use \Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Evaluation\Import\Parser\IParser;

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
     *
     * @return int - The newly assigned assess_id
	 */
	public function import()
    {
		$xml = $this->loadData();

		$this->checkValidQuizType($xml);

		$version = $this->getVersion($xml);
		$xmlFileParser = $this->getParser($xml, $version);

		$assess_id = $this->createEvaluation($xmlFileParser);

		if (!$assess_id) {
			error(112);
		}

		$this->setAssessId($assess_id);

		/* Process each section in the assess
		 */
		for ($i=0; $i < count($xml->section); $i++) {
			$currentSection = $xml->section[$i];

			$sectionData = array(
                'assess_id'   => $assess_id,
                'sequence'    => $i + 1,
                'title'       => trim($currentSection->title),
                'description' => trim((string)$currentSection->description->text) ? trim((string)$currentSection->description->text) : trim($currentSection->description),
                'random'      => ((int)$currentSection->random) ? 1 : 0,
                'pool'        => (int)$currentSection->pool,
            );
			$section_id = $this->getSectionHandler()->create($sectionData);

			/* Process each question in the section
			 */
			for ($j=0; $j < count($currentSection->question); $j++) {
				$currentQuestion = $currentSection->question[$j];

				$questionHandler = $this->getFactory()->createQuestionModel((string)$currentQuestion->typeid);
				$answerHandler = $this->getFactory()->createAnswerModel((string)$currentQuestion->typeid);

				$config = $this->getQuestionConfig($currentQuestion, $version);

				$question_id = $questionHandler->create(array(
                    'section_id'  => $section_id,
                    'sequence'    => $j + 1,
                    'type_id'     => $currentQuestion->typeid,
                    'marks'       => $currentQuestion->marks,
                    'question'    => trim((string)$currentQuestion->questiontext->text),
                    'config'      => $config,
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
     * @param SimpleXMLElement $question
     * @param string $version
     * @return string
     */
	protected function getQuestionConfig(SimpleXMLElement $question, $version)
    {
        /* Only json-encode if there are config elements
         *
         * When you cast the std obj to an array, you can possibly
         * end up with an empty config if the tags are separated
         */

        // Backwards compatibility for versions 16.0 -
        if(IParser::FORMAT_VERSION_160 == $version){
            if (count(array_keys((array)$question->config))) {
                return json_encode((array)$question->config);
            } else {
                return 'NULL';
            }
        }
        // 16.5 +
        else {
            /** @var SimpleXMLElement $configs */
            $configs = $question->configs->children();
            if($configs->count()){
                $config = [];
                foreach ($configs as $configSXE){
                    $config[(string)$configSXE->name] = (string)$configSXE->value;
                }
                return json_encode($config);
            } else {
                return 'NULL';
            }
        }
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
     * Creates the evaluation (without sections, questions or solutions)
     *
     * Returns the newly assigned 'assess_id'
     *
     * @param IParser $parser
     * @return int
     */
	abstract protected function createEvaluation(IParser $parser);


    /**
     * Gets a version-dependent parser that knows how to interpret a specific version format, but
     * respect a consistent API of representing the file's data.
     *
     * @param SimpleXMLElement $xml
     * @param string $fileFormatVersion
     * @return IParser
     */
	abstract protected function getParser(SimpleXMLElement $xml, $fileFormatVersion);

    /**
     * Returns what version file the user is trying to import.
     *
     * Prior to 16.5, no version numbering existed, so assume 16.0
     *
     * @param SimpleXMLElement $xml
     * @return string
     */
	protected function getVersion(SimpleXMLElement $xml)
    {
	    $fileVersion = (string)$xml->version;
	    return $fileVersion ?: Evaluation\Import\Parser\Base::FORMAT_VERSION_160;
    }

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
