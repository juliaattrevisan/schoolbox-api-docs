<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\StudentReportCard;

use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;
use Schoolbox\Resource\Category;
use SitePage;

class ContextSelector {

	const GRANULARITY_YEAR = 'year';
	const GRANULARITY_SEMESTER = 'sem';
	const GRANULARITY_TERM = 'term';

	/** @var string */
	protected $basePath;
	/** @var array */
	protected $years = array();
	/** @var Collection */
	protected $categories;
	/** @var Collection */
	protected $units;
	/** @var string */
	protected $_template = 'submission/_contextSelector.html';
	/** @var string */
	protected $granularity = self::GRANULARITY_SEMESTER;
	/** @var bool */
	protected $categoryFirst = false;


	/**
	 * @param string $basePath  The path to the page this selector is being used on (eg. "submission/allWork")
	 */
	public function __construct($basePath) {
		$this->basePath = $basePath;
		$this->categories = new Collection();
		$this->units = new Collection();
	}


	/**
	 * Set the years that the semester selector will display.
	 * If this array is empty, then the semester selector will be hidden.
	 *
	 * @param array $years
	 */
	public function setYears($years) {
		$this->years = $years;
	}


	/**
	 * Set the categories to display in the selector.
	 * If this collection is empty, then the category selector will be hidden.
	 *
	 * @param Collection $categories
	 */
	public function setCategories($categories) {
		$this->categories = $categories;
	}

	/**
	 * Set the units to display in the selector.
	 * If this collection is empty, then the unit selector will be hidden.
	 *
	 * @param Collection $units
	 */
	public function setUnits($units) {
		$this->units = $units;
	}

	/**
	 * Add a category to the selector.
	 *
	 * @param Category $category
	 */
	public function addCategory($category) {
		$this->categories->add($category);
	}


	/**
	 * Set the granularity for the semester selector (year, semester or term)
	 * (...don't use term)
	 *
	 * @param string $granularity
	 */
	public function setGranularity($granularity) {
		$this->granularity = $granularity;
	}

	/**
	 * @param bool $categoryFirst
	 */
	public function setCategoryFirst($categoryFirst) {
		$this->categoryFirst = $categoryFirst;
	}

	/**
	 * Only override the template if you absolutely must.
	 *
	 * @param string $template
	 */
	public function overrideTemplate($template) {
		$this->_template = $template;
	}


	/**
	 * Out. Put.
	 *
	 * @param SitePage $page            The SitePage instance into which we be rendering
	 * @param string   $handle          The parent handle into which we be rendering
	 * @param array    $selectedValues  Optionally, the elements to select by default; expects any of:
	 *                                     :year (usually from URI)
	 *                                     :semester (usually from URI)
	 *                                     :subject - the ID of the subject being targeted
	 */
	public function render($page, $handle = TEMPLATE_content, $selectedValues = array()) {
		$tpl = 'selectorTpl';

		$page->setFile($tpl, $this->_template);
		$page->setBlock($tpl, 'semester_context', 'semester_context_block');
		$page->setBlock($tpl, 'subject_context', 'subject_context_block');
		$page->setBlock($tpl, 'unit_context', 'unit_context_block');
		$page->setVar('PAGE_HREF',    $this->basePath);
		$page->setVar('SUBJECT_PART', is_numeric($selectedValues['category']) ? ($selectedValues['category'].'/') : '');
        $page->setVar('SEMESTER_CONTEXT_CATEGORY_ID', isset($selectedValues['list']) ? '/'.($selectedValues['list']) : '');

		// parse the semesters, if there are any
		if(count($this->years)) {
			$this->renderSemesterSelector($page, $selectedValues);
		}
		else {
			$page->setVar('semester_context_block', '');
		}

		// parse the subjects, if there are any
		if($this->categories->count()) {
			$this->renderCategorySelector($page, $selectedValues);
		}
		else {
			$page->setVar('subject_context_block', '');
		}

		if ($this->units->count()) {
			$this->renderUnitSelector($page, $selectedValues);
		} else {
			$page->setVar('unit_context_block', '');
		}

		// slam it in!
		$page->parse($handle, $tpl, false);
	}


	/**
	 * Return an array of the generated values for the semester selector
	 *
	 * @param $selectedValues
	 * @param string $direction
	 * @return array
	 */
	protected function createSemesterSelectorValues($selectedValues, $direction = 'DESC') {

		// create a little list
		switch($this->granularity) {
			case self::GRANULARITY_YEAR:
				$iterate = array('');
				$labelFormat = '%s%s'; // sprintf compatibility
				if(isset($selectedValues['year'])) {
					$selection = array($selectedValues['year']);
				}
				break;
			case self::GRANULARITY_TERM:
				$iterate = array(1,2,3,4);
				$labelFormat = 'Term %s, %s';
				if(isset($selectedValues['year']) && isset($selectedValues['semester'])) {
					$selection = array($selectedValues['year'], $selectedValues['semester']);
				}
				break;
			// should almost always be default (self::GRANULARITY_SEMESTER)
			default:
				$iterate = array(1,2);
				$labelFormat = 'Semester %s, %s';
				if(isset($selectedValues['year']) && isset($selectedValues['semester'])) {
					$selection = array($selectedValues['year'], $selectedValues['semester']);
				}
				break;
		}

		// reverse chronological order is the default behaviour
		if($direction == 'DESC') {
			$years = array_reverse($this->years, false);
			$semesters = array_reverse($iterate, false);
		}
		else {
			$years = $this->years;
			$semesters = $iterate;
		}

		$output = array();

		// if there is no selection, standard behaviour is default to the first element
		if(!isset($selection)) {
			$selection = array(reset($years), reset($semesters));
		}

		// iterate by year, then by semester
		foreach($years as $year) {
			foreach($semesters as $semester) {
				$option = array(
					'SEMESTER_SLUG'     => strlen($semester) ? "$year/$semester" : "$year",
					'SEMESTER_VALUE'    => sprintf($labelFormat, $semester, $year),
					'SEMESTER_SELECTED' => ''
				);

				if(array($year, $semester) == $selection) {
					$option['SEMESTER_SELECTED'] = 'selected="selected"';
					$output['nav'] = $this->calculateSemesterNavigation(count($iterate), $selection, $years);
				}

				$output['options'][] = $option;
			}
		}

        // Nothing was selected? Calculate navigation
		if(!isset($output['nav'])) {
			$output['nav'] = $this->calculateSemesterNavigation(count($iterate), array(reset($years), reset($semesters)), $years);
		}

		return $output;
	}


	/**
	 * Return an array of the generated values for the subject selector
	 *
	 * @param $selectedValues
	 * @return array
	 */
	protected function createSubjectSelectorValues($selectedValues) {

		$href = array();
		$output = array();

		if(isset($selectedValues['year'])) {
			$href[] = $selectedValues['year'];

			if(isset($selectedValues['semester'])) {
				$href[] = $selectedValues['semester'];
			}
		}

		$selectedId = isset($selectedValues['category']) ? $selectedValues['category'] : 0;

		foreach($this->categories as $category) {
			/** @var Category $category */

			$catId = $category->getId();

			// are we putting the category reference at the start or end of the href
			$thisHref = $href;
			if($this->categoryFirst) {
				array_unshift($thisHref, $catId);
			}
			else {
				array_push($thisHref, $catId);
			}

			$output[] = array(
				'SUBJECT_ID' => $catId,
				'SUBJECT_HREF' => implode('/', $thisHref),
				'SUBJECT_SELECTED' => $selectedId == $catId ? 'selected="selected"' : '',
				'SUBJECT_NAME' => $category->getName()
			);
		}

		return $output;
	}


	/**
	 * Figure out the BACK and FORWARD nav elements for the given parameters
	 *
	 * @param int   $size      The number of semesters
	 * @param array $selection The selected year and semester
	 * @param array $years     The available years - used to calculate if a
	 *                         back or forward nav should be disabled.
	 * @return array
	 */
	protected function calculateSemesterNavigation($size, $selection, $years) {
		list($year, $semester) = $selection;

		$nextYear = $year + 1;
		$lastYear = $year - 1;
		$backDisabled = false;
		$fwdDisabled  = false;
		if(is_numeric($semester)) {
			$nextSemester = $semester + 1;
			$lastSemester = $semester - 1;

			// wrapping backwards
			if ($lastSemester <= 0) {
				$back = "$lastYear/" . $size;
				if ($lastYear < min($years)) {
					$backDisabled = true;
				}
			}
			else {
				$back = "$year/$lastSemester";
			}

			// wrapping forwards
			if($nextSemester > $size){
				$fwd = "$nextYear/1";
				if ($nextYear > max($years)) {
					$fwdDisabled = true;
				}
			}
			else {
				$fwd = "$year/$nextSemester";
			}

		}
		// if there's no semester, just in/de-crement the year
		else {
			$back = $lastYear;
			$fwd = $nextYear;
			if ($year < min($years)) {
				$backDisabled = true;
			}
			if ($year > max($years)) {
				$fwdDisabled = true;
			}
		}

		return array(
			'SEMESTER_BACK' => $back,
			'SEMESTER_FWD'  => $fwd,
			'SEMESTER_BACK_DISABLED' => ($backDisabled ? 'disabled' : ''),
			'SEMESTER_FWD_DISABLED'  => ($fwdDisabled  ? 'disabled' : ''),
		);

	}


	/**
	 * Render the selector for semesters
	 *
	 * @param SitePage $page
	 * @param $selectedValues
	 */
	protected function renderSemesterSelector($page, $selectedValues) {
		$page->setBlock('semester_context', 'semester_option', 'semester_option_block');

		$semesters = $this->createSemesterSelectorValues($selectedValues);

		foreach($semesters['options'] as $option) {
			$page->setVar($option);
			$page->parse('semester_option_block', 'semester_option', true);
		}

		$page->setVar($semesters['nav']);

		// punch it
		$page->parse('semester_context_block', 'semester_context', false);
	}


	/**
	 * Render the selector for subjects (categories)
	 *
	 * @param SitePage $page
	 * @param $selectedValues
	 */
	protected function renderCategorySelector($page, $selectedValues) {
		$page->setBlock('subject_context', 'subject_option', 'subject_option_block');

		$subjects = $this->createSubjectSelectorValues($selectedValues);

		foreach($subjects as $subject) {
			$page->setVar($subject);
			$page->parse('subject_option_block', 'subject_option', true);
		}

		// make it so, number one
		$page->parse('subject_context_block', 'subject_context', false);
	}

	/**
	 * Render the selector for units
	 *
	 * Used on the progress report
	 *
	 * @param SitePage $page
	 * @param $selectedValues
	 */
	protected function renderUnitSelector($page, $selectedValues) {
		$page->setBlock('unit_context', 'unit_select', 'unit_select_block');
		$page->setBlock('unit_select', 'unit_option', 'unit_option_block');

		// Group units by year/semester
		$unit_year_list = [];
		foreach ($this->units as $unit) {
			/** @var UnitInstance $unit */
			$open = $unit->getOpenDate();
			$year = $open->getYear();
			switch ($this->granularity) {
				case self::GRANULARITY_YEAR:
					$key = $year;
					break;
				case self::GRANULARITY_TERM:
					// Don't use this, I guarantee nothing about it working
					$key = $year.'/'.ceil($open->getMonth() / 3);
					break;
				default:
					// should almost always be default (self::GRANULARITY_SEMESTER)
					$key = $year.'/'.$open->getYearHalf();
					break;
			}
			$unit_year_list[$key][] = $unit;
		}

		foreach ($unit_year_list as $key => $unit_list) {
			$page->setVar(
				array(
					'UNIT_KEY'          => $key,
					'unit_option_block' => '',
				)
			);
			foreach ($unit_list as $unit) {
				$page->setVar(
					array(
						'UNIT_NAME'     => $unit->getTitle(),
						'UNIT_ID'       => $unit->getId(),
						'UNIT_SELECTED' => ($unit->getId() == $selectedValues['unit'] ? 'selected' : ''),
					)
				);
				$page->parse('unit_option_block', 'unit_option', true);
			}
			$page->parse('unit_select_block', 'unit_select', true);
		}

		// make it so, number one
		$page->parse('unit_context_block', 'unit_context', false);
	}
}
