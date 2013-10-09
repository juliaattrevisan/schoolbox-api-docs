<?php
/**
 * Stub mapper for categories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;
use Schoolbox\Collection;


class Category {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var Category
	 */
	protected $parent;

    /**
     * @var \Schoolbox\Resource\HomepageConfig
     */
    protected $homepage;

	/**
	 * @var Collection
	 */
	protected $codes;

    /**
     *
     * @var \Schoolbox\Resource\Group
     */
    protected $group;

	public function __construct() {
		$this->setCodes(new Collection(array()));
	}

    /**
     * Does this category have a homepage?
     *
     * @return bool
     */
    public function hasHomepage() {
        return ($this->homepage instanceof HomepageConfig && $this->homepage->getTemplate()->getId());
    }

	/**
	 * @param  Collection $codes
	 * @return $this
	 */
	public function setCodes($codes) {
		$this->codes = $codes;
		return $this;
	}

	/**
	 * @return Collection
	 */
	public function getCodes() {
		return $this->codes;
	}

	/**
	 * @param  CategoryCode $code
	 * @return $this
	 */
	public function addCode($code) {
		$this->getCodes()->add($code);
		return $this;
	}

	/**
	 * @param  int $id
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  string $name
	 * @return $this
	 */
	public function setName($name) {
		$this->name = $name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param  Category $parent
	 * @return $this
	 */
	public function setParent($parent) {
		$this->parent = $parent;
		return $this;
	}

	/**
	 * @return Category
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * @param \Schoolbox\Resource\HomepageConfig $homepage
	 * @return $this
	 */
	public function setHomepage($homepage) {
		$this->homepage = $homepage;
		return $this;
	}
	/**
	 * @return \Schoolbox\Resource\HomepageConfig
	 */
	public function getHomepage() {
		return $this->homepage;
	}

    /**
     * @param \Schoolbox\Resource\Group $group
     * @return $this
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\Group
     */
    public function getGroup()
    {
        return $this->group;
    }

	//
	// TODO implement group type, user access joins
//	protected $user_access;


}