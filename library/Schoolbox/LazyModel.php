<?php
/**
 * Wrapper class for lazy-loaded models.
 *
 * Provides exactly the same external methods as whatever model you are lazy
 * loading.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\Mapper;

class LazyModel {
	/**
	 * @var Mapper
	 */
	protected $mapper;

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var object
	 */
	protected $loaded_model;

	/**
	 * Creates lazy loaded model.
	 *
	 * @param Mapper $mapper The mapper to fetch the model from. Currently uses
	 *                       findById() to fetch result.
	 * @param int    $id     The ID of the model to retrieve.
	 */
	public function __construct(Mapper $mapper, $id) {
		$this->mapper = $mapper;
		$this->id     = $id;
	}

	/**
	 * Has the actual model been loaded yet?
	 *
	 * @return bool
	 */
	public function isModelLoaded() {
		return isset($this->loaded_model);
	}

	/**
	 * Loads model from mapper.
	 *
	 * @return void
	 */
	protected function loadModel() {
		$this->loaded_model = $this->mapper->findById($this->id);
	}

	/**
	 * Magic call method - loads model if it isn't loaded yet, and passes method
	 * through to loaded model.
	 *
	 * If the identifier is being requested, then the
	 *
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function __call($method, $args) {
		//
		// Load model, unless this is retrieving the identifier. (How to tell?)
		$identifier_method = $this->mapper->getGetIdentifierMethodName();
		if (!$this->isModelLoaded()) {
			if ($method === $identifier_method) {
				return $this->id;
			}
			$this->loadModel();
		}
		return call_user_func_array(array($this->loaded_model, $method), $args);
	}
}