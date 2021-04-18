<?php
/**
 * Evaluation_Export_Xml
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
abstract class Evaluation_Export_Xml
{
    /**  @var array */
	private $assess;

    /** @var Evaluation_Section */
    private $sectionHandler;

	/**  @var Evaluation_Question */
    private $questionHandler;

	/** @var Evaluation_Factory */
    private $factory;

	/** @var SitePage */
    private $page;

	/**
	 * Constructor
	 *
	 * @param array $assess
	 * @param Evaluation_Factory $factory
	 * @param Evaluation_Section $sectionHandler
	 * @param Evaluation_Question $questionHandler
	 * @param SitePage $page
	 */
	public function __construct($assess, $factory, $sectionHandler, $questionHandler, $page)
    {
		$this->setAssess($assess);
		$this->setSectionHandler($sectionHandler);
		$this->setQuestionHandler($questionHandler);
		$this->setFactory($factory);
		$this->setPage($page);
	}

	/**
	 * Set up xml content
	 */
	protected function setupBlocks()
    {
		$this->getPage()->setFile('xml', $this->getTemplateFile());
		$this->getPage()->setBlock('xml', 'section', 'section_block');
        $this->getPage()->setBlock('section', 'question', 'question_block');
        $this->getPage()->setBlock('question', 'answer_configuration', 'answer_configuration_block');
		$this->getPage()->setBlock('question', 'answerInputs', 'answerInputs_block');
	}

    /**
     * Set the assess (quiz|survey) data using most-authoritative source
     *
     * @param mixed[] $assess
     */
	protected function renderEvaluation(array $assess)
    {
        $this->getPage()->setVar([
            'ASSESS_COMPLETION_MESSAGE' => Scrub::HTML($assess['completion_message']),
            'ASSESS_MAX_ATTEMPTS'       => $assess['max_attempts'],
            'ASSESS_AVAILABILITY'       => $assess['availability'],
        ]);

        $this->renderSections();
    }


    /**
     * Set the section data
     */
    protected function renderSections()
    {
        $sections = $this->getSectionHandler()->getList($this->assess['assess_id']);

        // Do we not have any sections to render?
        if(!is_array($sections) || !count($sections)){
            // Bug out early.
            $this->getPage()->setVar('section_block', '');
            return;
        }

        foreach($sections as $currentSection) {
            $this->renderSection($currentSection);
        }
    }

    /**
     * Render out the specified section
     *
     * @param array $section
     */
    protected function renderSection(array $section)
    {
        $this->getPage()->setVar([
            'SECTION_TITLE'       => htmlspecialchars($section['title'], ENT_QUOTES),
            'SECTION_DESCRIPTION' => Scrub::HTML($section['description']),
        ]);

        $allQuestions = $this->sectionHandler->getContents($this->assess['assess_id'], $section['section_id']);

        // Filter questions that don't have a type (not sure if this still necessary)
        $allQuestions = (new \Schoolbox\Collection($allQuestions))->filter(function($question){
            return $question['type_id'];
        });

        if ($allQuestions) {
            foreach($allQuestions as $idx => $currentQuestion) {
                $this->renderQuestion($currentQuestion, $idx > 0);
            }
        }
        else {
            $this->getPage()->setVar('question_block', '');
        }

        $this->getPage()->parse('section_block', 'section', true);
    }

    /**
     * Render a question inside a section
     *
     * @param array $question
     * @param bool $append   Whether to append the question to the current section, or start new section
     */
    protected function renderQuestion(array $question, $append)
    {
        // Get meta for this type of question
        $questionTypeMeta = $this->getQuestionHandler()->getQuestionType($question['type_id']);

        $this->getPage()->setVar([
            'QUESTION_QUESTION'    => Scrub::Html($question['question']),
            'QUESTION_MARKS'       => $question['marks'],
            'QUESTION_TYPE_ID'     => $question['type_id'],
            'QUESTION_TYPE_MOODLE' => $this->getFactory()->getMoodleQuestionType($question['type_id']),
        ]);

        $answerHandler = $this->getFactory()->createAnswerModel($question['type_id']);

        /* Question-specific answer configuration
         *
         * Load answer configurations template into answer_configuration_form_fragment
         * block if there are configuration options available
         */
        if ($questionTypeMeta['has_config'] == 'true') {
            $answerHandler->configureXml($this->getPage(), $question['config']);
        } else {
            $this->getPage()->setVar([
                'answer_configuration_block' => ''
            ]);
        }

        // Load answer template into answerOption block

        // Yes, we really have to call this! (sitepage will silently ignore a call to load a different file to the same handle name)
        $this->getPage()->setVar('answerFormFragment', '');

        $this->getPage()->setFile('answerFormFragment', $this->getEvaluationType() . '/answer/' . $this->getFactory()->getQuestionType($question['type_id']) . '/exportToMoodle.xml');
        $this->getPage()->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        // Render Answer
        $answerHandler->exportMoodleAnswer($this->getPage(), $question['question_id']);
        $this->getPage()->parse('answerInputs_block', 'answerFormFragment');

        // Render Question
        $this->getPage()->parse('question_block', 'question', $append);
    }

    /**
     * Set up all PHP-Lib vars
     */
    protected function setVars()
    {
        $this->renderEvaluation($this->assess);
    }


	/**
	 * Render headers and xml data
	 */
	public function render()
    {
        $this->setupBlocks();
        $this->setVars();
        $this->parse();
	}

    protected function parse()
    {
        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename="' . $this->generateFilename() . '"');

        $this->page->injectGlobalVars();
        $this->page->template->pparse('finaloutput', 'xml');
        die();
    }

	/**
	 * Retrieves the XML content of the export, without outputting it.
	 *
	 * @return string
	 */
	public function retrieveContents() {
		return $this->getPage()->template->parse('finaloutput', 'xml');
	}

	/**
	 * Generate a filename for the exported xml
	 *
	 * @return string
	 */
	protected function generateFilename() {
		$assess = $this->getAssess();

		$filename = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', $assess['title']) . '-' . date('YmdHis') . '.xml';

		return $filename;
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
	 * @param array $assess
	 */
	protected function setAssess($assess) {
		$this->assess = $assess;
	}

	/**
	 * @return array
	 */
	protected function getAssess() {
		return $this->assess;
	}

	/**
	 * @param Evaluation_Section $sectionHandler
	 */
	protected function setSectionHandler($sectionHandler) {
		$this->sectionHandler = $sectionHandler;
	}

	/**
	 * @return Evaluation_Section
	 */
	protected function getSectionHandler() {
		return $this->sectionHandler;
	}

	/**
	 * Get template file
	 *
	 * @return string
	 */
	abstract protected function getTemplateFile();

	/**
	 * Get evaluation type
	 *
	 * @return string
	 */
	abstract protected function getEvaluationType();

	/**
	 * @param Evaluation_Question $questionHandler
	 */
	protected function setQuestionHandler($questionHandler) {
		$this->questionHandler = $questionHandler;
	}

	/**
	 * @return Evaluation_Question
	 */
	protected function getQuestionHandler() {
		return $this->questionHandler;
	}
}
