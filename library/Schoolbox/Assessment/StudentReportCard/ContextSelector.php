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
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Model as Folder;
use SitePage;

class ContextSelector
{
	/** @var string */
	protected $basePath;

	/**
	 * The term objects that may be selected.
	 *
	 * @var Term\Model[]|Collection
	 */
	protected $terms;

	/** @var Collection */
	protected $categories;
	/** @var Collection */
	protected $units;
	/** @var string */
	protected $_template = 'submission/_contextSelector.html';

	/** @var bool */
	protected $categoryFirst = false;

	/** @var bool */
	protected $usePlugin = true;

	/**
	 * @param string $basePath  The path to the page this selector is being used on (eg. "submission/allWork")
	 */
	public function __construct($basePath) {
		$this->basePath = $basePath;
		$this->categories = new Collection();
		$this->units = new Collection();
		$this->terms = new Collection();
	}

	/**
	 * @param  Term\Model[] $terms
	 * @return $this
	 */
	public function setTerms($terms)
	{
		$this->terms = $terms;
		return $this;
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
	 * @param Folder $category
	 */
	public function addCategory($category) {
		$this->categories->add($category);
	}

	/**
	 * @param bool $categoryFirst
	 */
	public function setCategoryFirst($categoryFirst) {
		$this->categoryFirst = $categoryFirst;
	}

	/**
	 * If false, don't setup the contextSelector plugin - allow the including
	 * template to wire all events together.
	 *
	 * @param bool $usePlugin
	 */
	public function setUsePlugin($usePlugin)
	{
		$this->usePlugin = $usePlugin;
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
	 *                                     :subject - the ID of the subject being targeted
	 */
	public function render($page, $handle = TEMPLATE_content, $selectedValues = array()) {
		$tpl = 'selectorTpl';

		$page->setFile($tpl, $this->_template);
		$page->setBlock($tpl, 'term_context', 'term_context_block');
		$page->setBlock($tpl, 'subject_context', 'subject_context_block');
		$page->setBlock($tpl, 'unit_context', 'unit_context_block');
		$page->setVar('USE_PLUGIN',   $this->usePlugin);
		$page->setVar('PAGE_HREF',    $this->basePath);
		$page->setVar('SUBJECT_PART', is_numeric($selectedValues['category']) ? ($selectedValues['category'].'/') : '');
        $page->setVar('TERM_CONTEXT_CATEGORY_ID', isset($selectedValues['list']) ? '/'.($selectedValues['list']) : '');

		// parse the terms, if there are any
		if(count($this->terms)) {
			$this->renderTermSelector($page, $selectedValues);
		}
		else {
			$page->setVar('term_context_block', '');
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
	 * Return an array of the generated values for the term selector
	 *
	 * @param $selectedValues
	 * @param string $direction
	 * @return array
	 */
	protected function createTermSelectorValues($selectedValues, $direction = 'DESC')
	{
		$selection = $selectedValues['term'];

		// reverse chronological order is the default behaviour
		if ($direction == 'DESC') {
			$this->terms->sortBy('start', 'DESC');
		} else {
			$this->terms->sortBy('start', 'ASC');
		}

		$output = array();

		// if there is no selection, standard behaviour is default to the first element
		if(!isset($selection)) {
			$selection = $this->terms->first();
		}

		foreach ($this->terms as $term) {
			$termValue = sprintf(
				'%s (%s—%s)',
				$term->getTitle(),
				$term->getStart()->getDateOnly(),
				$term->getDisplayFinish()->getDateOnly()
			);
			if ($term->getArchived()) {
				$termValue = '(archived) '.$termValue;
			}
			$option = array(
				'TERM_SLUG'     => $term->getCode(),
				'TERM_VALUE'    => $termValue,
				'TERM_SELECTED' => ''
			);
			if ($term->getId() == $selection->getId()) {
				$option['TERM_SELECTED'] = 'selected="selected"';
			}
			$output['options'][] = $option;
		}
		$output['nav'] = $this->calculateTermNavigation($selection);
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

		if ($selectedValues['term'] instanceof Term\Model) {
			$href[] = $selectedValues['term']->getCode();
		}

		$selectedId = isset($selectedValues['category']) ? $selectedValues['category'] : 0;

		foreach($this->categories as $category) {
			/** @var Folder $category */

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
				'SUBJECT_NAME' => $category->getNameWithCodes()
			);
		}

		return $output;
	}


	/**
	 * Figure out the BACK and FORWARD nav elements for the given parameters
	 *
	 * @param  Term\Model $selection The selected term
	 * @return array
	 */
	protected function calculateTermNavigation(Term\Model $selection) {
		$result = [
			'TERM_BACK'          => '',
			'TERM_FWD'           => '',
			'TERM_BACK_DISABLED' => 'disabled',
			'TERM_FWD_DISABLED'  => 'disabled',
		];

		// I'd like an iterator which returns current, previous and next as a triple
		/** @var Term\Model[] $terms */
		$terms = array_values($this->terms->getAll());

		if ($selection->getId() !== null) {
			foreach ($terms as $i => $term) {
				$prevTerm = $terms[$i - 1];
				$nextTerm = $terms[$i + 1];
				if ($term->getId() == $selection->getId()) {
					if (isset($prevTerm)) {
						$result['TERM_FWD']          = $prevTerm->getCode();
						$result['TERM_FWD_DISABLED'] = '';
					}
					if (isset($nextTerm)) {
						$result['TERM_BACK']          = $nextTerm->getCode();
						$result['TERM_BACK_DISABLED'] = '';
					}
				}
			}
		}

		return $result;
	}


	/**
	 * Render the selector for terms
	 *
	 * @param SitePage $page
	 * @param $selectedValues
	 */
	protected function renderTermSelector($page, $selectedValues) {
		$page->setBlock('term_context', 'term_option', 'term_option_block');

		$terms = $this->createTermSelectorValues($selectedValues);

		foreach($terms['options'] as $option) {
			$page->setVar($option);
			$page->parse('term_option_block', 'term_option', true);
		}

		$page->setVar($terms['nav']);

		// punch it
		$page->parse('term_context_block', 'term_context', false);
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
	 * Render the selector for units. Units will be grouped by term if terms have been set.
	 *
	 * Used on the progress report
	 *
	 * @param SitePage $page
	 * @param $selectedValues
	 */
	protected function renderUnitSelector($page, $selectedValues) {
		$page->setBlock('unit_context', 'unit_select', 'unit_select_block');
		$page->setBlock('unit_select', 'unit_option', 'unit_option_block');

		// Group units by term (assuming there are terms!)
		$unit_year_list = [];
		if (count($this->terms) === 0) {
			$unit_year_list[''] = $this->units;
		} else {
			$this->terms->sortBy('start', 'asc');
			/** @var Term\Model[] $terms */
			$terms = array_values($this->terms->getAll());

			// Add a selector for all terms without units
			foreach ($terms as $term) {
				$unit_year_list[$term->getCode()] = [];
			}

			// Add unit to term
			foreach ($this->units as $unit) {
				/** @var UnitInstance $unit */
				foreach ($terms as $i => $term) {
					$nextTerm = $terms[$i+1];
					if (!isset($nextTerm) || $term->contains($unit->getOpenDate())) {
						// Last term - put this unit in this term
						// Unit starts between this and next term's start
						$unit_year_list[$term->getCode()][] = $unit;
						break;
					}
				}
			}
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
