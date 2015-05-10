<?php
/**
 * Data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Usage\Specific;

class AjaxFlot {

	/**
	 * An instance of UsageMapper
	 *
	 * @var UsageMapper
	 */
	private $mapper = null;

	/**
	 * Constructor
	 *
	 * @param $userId
	 */
	public function __construct($userId) {
		$this->setMapper(new UsageMapper($userId));
	}

	/**
	 * Delegation method
	 *
	 * Find total usage
	 *
	 * @return bool|float|int|string
	 */
	public function findTotalUsage() {
		$used = $this->getMapper()->findTotalUsage();

		$result = array(
			'label' => 'Used',
			'data' => (int)$used,
		);

		return json_encode($result);
	}

	/**
	 * Delegation method
	 *
	 * Find total group usage
	 *
	 * @return bool|float|int|string
	 */
	public function findGroupUsage() {
		$allData = $this->getMapper()->findGroupUsage();
		$allResults = array();

		for ($i = 0; $i < count($allData); $i++) {
			$currentData = $allData[$i];

			$result = array();
			$result['label'] = $currentData['name'];
			$result['data'] = (int)$currentData['bytes'];

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Calculate percentage
	 *
	 * @param $top
	 * @param $bottom
	 * @return float|int
	 */
	private function percentage($top, $bottom) {
		if (!$bottom) {
			return 0;
		}

		return 100*$top/$bottom;
	}

	/**
	 * Delegation method
	 *
	 * Find total type usage and pie it out, which uses a percentage
	 *
	 * @return bool|float|int|string
	 */
	public function findTypeUsage() {
		$allData = $this->getMapper()->findTypeUsage();

		$allResults = array();
		$totalBytes = 0;

		/* Find the total
		 */
		foreach ($allData as $currentData) {
			$totalBytes += $currentData['bytes'];
		}

		/* Find each segment
		 */
		foreach ($allData as $currentData) {
			$result = array();
			$result['label'] = $currentData['name'];
			$result['data'] = $this->percentage($currentData['bytes'], $totalBytes);

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Delegation method
	 *
	 * Find total file usage
	 *
	 * @param $orderBy
	 * @param $order
	 * @param $index
	 * @param $numPerPage
	 */
	public function findFileUsage($orderBy, $order, $index, $numPerPage) {
		$allData = $this->getMapper()->findFileUsage($orderBy, $order, $index, $numPerPage);

		$page = new \SitePage();
		$page->setFile('file_list', '/adminv2/usage/file.html');
		$page->setBlock('file_list', 'file', 'file_block');
        $page->setBlock('file', 'file_storage', 'file_storage_block');
        $page->setBlock('file', 'file_submission', 'file_submission_block');
        $page->setBlock('file', 'file_sbfs', 'file_sbfs_block');
        $page->setBlock('file', 'has_category', 'has_category_block');
        $page->setBlock('file', 'no_category', 'no_category_block');

        $page->setBlock('file', 'file_myfile', 'file_myfile_block');
        $page->setBlock('file', 'file_unlinked', 'file_unlinked_block');

        $page->setVar(array(
            'file_storage_block' => '',
            'file_submission_block' => '',
            'file_sbfs_block' => '',
            'file_myfile_block' => '',
            'file_unlinked_block' => '',
            'has_category_block' => '',
            'no_category_block' => '',
        ));

		/* Really? You should be passing the bytes as constructor arg
		 */
		$bytes = new \Alaress_Byte_Format();

//        var_dump($allData);
		foreach ($allData as $currentData) {
			$page->setVar(array(
                'FILE_ID' => $currentData['id'],
				'FILE_FILENAME' => $currentData['name'],
				'FILE_BYTES' => $bytes->bytesToString($currentData['bytes']),
				'FILE_GROUPNAME' => $currentData['groupName'],
				'FILE_MIMETYPE' => $currentData['mime_type'],
			));

            /* Link Storage files
             */
            if ($currentData['groupName'] == 'Storage') {
                $hash = $currentData['hash'];

                $page->setVar(array(
                    'FILE_HASH' => $hash,
                    'FILE_LOCATION' => 'storage',
                ));

                $page->parse('file_storage_block', 'file_storage');
                $page->parse('no_category_block', 'no_category');
            }
            /* Link SBFS files
             */
            else if ($currentData['groupName'] == 'File') {
                $page->setVar(array(
                    'FILE_CATEGORY_ID' => $currentData['categoryId'],
                    'FILE_CATEGORY_NAME' => $currentData['categoryName'],
                    'FILE_LOCATION' => 'sbfs',
                ));

                $page->parse('file_sbfs_block', 'file_sbfs');
                $page->parse('has_category_block', 'has_category');
            }
            /* Link MyFile
             */
            else if ($currentData['groupName'] == 'My File') {
                $page->parse('file_myfile_block', 'file_myfile');
                $page->parse('no_category_block', 'no_category');
                $page->setVar(array(
                    'FILE_LOCATION' => 'myfile',
                ));
            }
            /* Student Submissions
             */
            else if ($currentData['groupName'] == 'Submission') {
                $page->parse('file_submission_block', 'file_submission');
                $page->parse('has_category_block', 'has_category');
                $page->setVar(array(
                    'FILE_LOCATION' => 'submission',
                ));
            }
//            else if ($currentData['groupName'] == 'Return') {
//                $page->parse('file_unlinked_block', 'file_unlinked');
//                $page->parse('no_category_block', 'no_category');
//                $page->setVar(array(
//                    'FILE_LOCATION' => 'return',
//                ));
//            }
            /* None of the above
             */
            else {
                $page->parse('file_unlinked_block', 'file_unlinked');
                $page->parse('no_category_block', 'no_category');
                $page->setVar(array(
                    'FILE_LOCATION' => 'unlinked',
                ));
            }

			$page->parse('file_block', 'file', true);

            /* Clear children after parsing out the parent
             */
            $page->setVar(array(
                'file_storage_block' => '',
                'file_submission_block' => '',
                'file_sbfs_block' => '',
                'file_myfile_block' => '',
                'file_unlinked_block' => '',
                'has_category_block' => '',
                'no_category_block' => '',
            ));
		}

		echo $page->template->_varVals['file_block'];
	}

	/* Getters/Setters
	 */

	/**
	 * Set mapper
	 *
	 * @param UsageMapper $mapper
	 */
	private function setMapper($mapper) {
		$this->mapper = $mapper;
	}

	/**
	 * Get mapper
	 *
	 * @return UsageMapper
	 */
	public function getMapper() {
		return $this->mapper;
	}
}