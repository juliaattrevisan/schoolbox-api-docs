<?php
/**
 * Bulk_Category
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_Category extends Bulk_Abstract {
	private $templateID = 0;

	/**
	 * Constructor
	 *
	 * @param $file
	 * @param $templateID
	 */
	public function __construct($file, $templateID) {
		$this->setFile($file);
		$this->setTemplateID($templateID);
	}

	/**
	 * Adds categories via csv
	 * CSV Fields:
		 0.	parent
		 1.	title
		 2-n. codes
	 *
	 * @return array
	 */
	public function addCategories() {
		$categoryHandler = new Category();

		/* List of outcomes
		 */
		$output = array();

		if (is_file($this->getFile())) {
			$groups = array_map(array(Scrub, 'text'), $_POST['groupPerm']);

			/* Process rows
			 */
			$handle = fopen($this->getFile(), 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($category = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if (($category[0] != '') && ($category[1] != '')) {

					/* Check parent exists
					 */
					if ($categoryHandler->getByID($category[0]) == FALSE) {
						$output[] = array(
							'category' => $category[1],
							'status' => 'Not Added',
							'error' => "Could not find parent folder {$category[0]}",
						);

						continue;
					}

					/* Check if subject code already exists
					 */
					$codeExists = false;
					$code = array();

					for ($i=2; $category[$i]; $i++) {
						if ($categoryHandler->getByCode($category[$i])) {
							$output[] = array(
								'category' => $category[$i],
								'status' => 'Skipped',
								'error' => "Subject code already exists ({$category[$i]})",
							);

							$codeExists = true;
						}

						$code[] = $category[$i];
					}

					if ($codeExists) {
						continue;
					}

					$newCat = array(
						'name' => $category[1],
						'parent' => $category[0],
						'template' => $this->getTemplateID(),
						'group' => $groups,
						'code' => implode(',', $code),
					);

					/* Successfully created
					 */
					if ($categoryHandler->create($newCat)) {
						$output[] = array(
							'category' => $category[1],
							'status' => 'Added',
							'error' => 'OK',
						);
					}
				}
			}
		}

		return $output;
	}

	/* Getters/Setters
	 */

	/**
	 * Set template id
	 *
	 * @param $templateID
	 */
	private function setTemplateID($templateID) {
		$this->templateID = $templateID;
	}

	/**
	 * Get template id
	 *
	 * @return int
	 */
	private function getTemplateID() {
		return $this->templateID;
	}
}
