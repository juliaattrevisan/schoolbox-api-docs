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
	 * The identifier that identifies the model to retrieve when called.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * @var object
	 */
	protected $loaded_model;

	/**
	 * If set, this column is used instead of the identifier when loadModel() is
	 * called.
	 *
	 * @var string
	 */
	protected $column = null;

	/**
	 * If set, this property is identified as the ID instead of the identifier
	 * when a method call is passed through __call() to the
	 * called.
	 *
	 * @var string
	 */
	protected $property = null;

	/**
	 * Creates lazy loaded model.
	 *
	 * @param Mapper $mapper   The mapper to fetch the model from.
	 * @param int    $id       The identifier of the model to retrieve.
	 * @param string $column   If set, this column is used instead of the ID to
	 *                         load the model when loadModel() is called.
	 * @param string $property If set, this property is used instead of the ID
	 *                         property when passing through to the model.
	 *
	 */
	public function __construct(Mapper $mapper, $id, $column = null, $property = null) {
		$this->mapper   = $mapper;
		$this->id       = $id;
		$this->column   = $column;
		$this->property = $property;
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
		$this->loaded_model = isset($this->column) ?
			$this->mapper->findOneByColumn($this->column, $this->id):
			$this->mapper->findById($this->id);
	}

	/**
	 * Magic call method - loads model if it isn't loaded yet, and passes method
	 * through to loaded model.
	 *
	 * If the identifier is being requested, then it's loaded from this class
	 * if possible, instead of unnecessarily loading from the database.
	 *
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function __call($method, $args) {
		$identifier_method = isset($this->property) ?
			$this->mapper->getGetMethodName($this->property):
			$this->mapper->getGetIdentifierMethodName();
		if (!$this->isModelLoaded()) {
			if ($method === $identifier_method) {
				return $this->id;
			}
			$this->loadModel();
		}
		return call_user_func_array(array($this->loaded_model, $method), $args);
	}
}