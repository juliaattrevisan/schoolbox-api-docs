<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Instance;

use Alaress\Async\Notification\Parameter\ParameterInterface;
use Html2Text\Html2Text;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Builds instances of notifications. Factory pattern.
 *
 * Class Builder
 *
 * @package Alaress\Async\Notification
 */
class Builder {

    /** @var bool */
    protected $validate;

	/** @var array */
	protected $context;

	/** @var array */
	protected $topics;

	/** @var int */
	protected $severity;

	/** @var ParameterInterface[] */
	protected $params;

	/** @var string */
	protected $title;

	/** @var array */
	protected $meta;

	/** @var string */
	protected $icon;

	/** @var string */
	protected $href;

	/** @var int */
	protected $time;

	/** @var string */
	protected $namespace;


	/**
	 * Basic construction
     *
     * @param bool $validate optionally build without validation (debug only), if validation fails exception thrown
	 */
	public function __construct($validate = true) {
        $this->validate = $validate;
		$this->clear();
	}


	/**
	 * Sets up all vars
	 * @return self
	 */
	public function clear() {
		$this->context   = array();
		$this->topics    = array();
		$this->params    = array();
		$this->meta      = array();

		$this->namespace = '';
		$this->title     = '';
		$this->icon      = '';
		$this->href      = '';

		$this->severity  = 0;
		$this->time      = 0;

		return $this;
	}


	/**
	 * @param string $namespace
	 * @return self
	 */
	public function setNamespace($namespace) {
		$this->namespace = $namespace;
		return $this;
	}


	/**
	 * @param string $href
	 * @return self
	 */
	public function setHref($href) {
		$this->href = $href;
		return $this;
	}


	/**
     * Set body, converting to plaintext as you go (we can't support markup in the body)
     *
	 * @param string $body
	 * @return self
	 */
	public function setBody($body) {
        // totally strip links
        $plain = new Html2Text($body, false, array('do_links' => 'none'));
        return $this->putMeta('body', $plain->get_text());
	}


	/**
	 * @param string $icon
	 * @return self
	 */
	public function setIcon($icon) {
		$this->icon = $icon;
		return $this;
	}


	/**
	 * @param int $time
	 * @return self
	 */
	public function setTimestamp($time) {
		$this->time = $time;
		return $this;
	}


	/**
	 * @param string $title
	 * @return self
	 */
	public function setTitle($title) {
		$this->title = $title;
		return $this;
	}


	/**
	 * @param string $type
	 * @param array $ids
	 * @return self
	 */
	public function addFilter($type, $ids) {
		$this->context[$type] = $ids;
		return $this;
	}

	/**
	 * @param array $filter
	 * @return self
	 */
	public function setFilter($filter) {
		$this->context = array_merge($this->context, $filter);
		return $this;
	}


	/**
	 * @param string $byline
	 * @return self
	 */
	public function setByline($byline) {
		return $this->putMeta('byline', $byline);
	}


	/**
	 * @param $name
	 * @param $value
	 * @return self
	 */
	public function putMeta($name, $value) {
		$this->meta[$name] = $value;
		return $this;
	}


	/**
	 * @param array $meta
	 * @return self
	 */
	public function setMeta($meta) {
		$this->meta = $meta;
		return $this;
	}


	/**
	 * @param string $token
	 * @param ParameterInterface $param
	 * @return self
	 */
	public function setParam($token, ParameterInterface $param) {
		$this->params[$token] = $param;
		return $this;
	}

    /**
     * The opposite of setParam()
     *
     * @param string $token
     *
     * @return self
     */
    public function unsetParam($token)
    {
        unset($this->params[$token]);
        return $this;
    }

	/**
	 * @param ParameterInterface[] $params
	 * @return self
	 */
	public function setParams($params) {
		$this->params = array();
		foreach($params as $t => $p) {
			$this->setParam($t, $p);
		}
		return $this;
	}


	/**
	 * @param int $severity
	 * @return self
	 */
	public function setSeverity($severity) {
		$this->severity = $severity;
		return $this;
	}


	/**
	 * @param string $topic
	 * @return self
	 */
	public function addTopic($topic) {
		$this->topics[] = $topic;
		return $this;
	}


	/**
	 * @param array $topics
	 * @return self
	 */
	public function setTopics($topics) {
		$this->topics = (array)$topics;
		return $this;
	}


	/**
	 * Builds an instance from the construction given
	 *
	 * @throws OutOfBoundsException
	 * @throws InvalidArgumentException
	 * @return Instance
	 */
	public function build() {
		// check things are right

		if($this->validate) {
			// mandatory strings
			$check = array('title', 'icon', 'namespace', 'href');

			foreach($check as $prop) {
				if(!(trim($this->$prop))) {
					throw new InvalidArgumentException(ucfirst($prop)." has not been set");
				}
			}

			if($this->severity == 0) {
				throw new OutOfBoundsException("Severity has not been set");
			}

			if(!count($this->topics)) {
				throw new InvalidArgumentException("Topic has not been set");
			}
		}

		return new Instance(
			// mandatory args
			$this->title, $this->namespace, $this->icon, $this->href,
			$this->params, $this->severity, $this->topics,
			// optional args
			$this->time, $this->meta, $this->context
		);
	}

}