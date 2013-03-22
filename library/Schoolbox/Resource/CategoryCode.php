<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;


class CategoryCode {
	/**
	 * @var int
	 */
	protected $category_id;

	/**
	 * @var string
	 */
	protected $code;

	/**
	 * @param  int $category_id
	 * @return $this
	 */
	public function setCategoryId($category_id) {
		$this->category_id = $category_id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getCategoryId() {
		return $this->category_id;
	}

	/**
	 * @param  string $code
	 * @return $this
	 */
	public function setCode($code) {
		$this->code = $code;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCode() {
		return $this->code;
	}

}