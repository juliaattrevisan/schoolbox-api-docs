<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;
use Schoolbox\ISoftDeletable;

class CategoryCode implements ISoftDeletable{
	/**
	 * @var Category
	 */
	protected $category;

	/**
	 * @var string
	 */
	protected $code;

    /**
     * @var \Alaress_Date_Format
     */
    protected $deletedAt;

    /**
	 * @param  Category $category
	 * @return $this
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @return Category
	 */
	public function getCategory() {
		return $this->category;
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

    /**
     * @param \Alaress_Date_Format $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format);
    }

}