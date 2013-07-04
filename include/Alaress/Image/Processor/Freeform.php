<?php
/**
 * Alaress_Image_Processor_Freeform
 *
 * This allows you to perform certain transformations dynamically, passing
 * arguments into the transformation defintion from the querystring.
 *
 * This is accomplished via the Transformation class, demonstrated in MyImage.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Freeform extends Alaress_Image_Processor_Standard {

	/**
	 * The transform key to use, defaults to "Constrain".
	 *
	 * @var string
	 */
	protected $transform_key = 'Constrain';

	/**
	 * Perform all transformations if we didn't load an image from the cache.
	 * Otherwise transformations were done previously and saved, and we can skip
	 * this step entirely
	 *
	 * @return $this
	 */
	public function transformAll() {

		/* Retrieve the list of transformations and do 'em, if we haven't already
		 * done them at some point in the past
		 */
		if (!$this->getCache()->isLoadedFromCache()) {

			/* 'Constrain' can be converted into an $_GET querystring parameter
			 * or rammed into the constructor as an additional param, if you want
			 * other parameterised transformation definitions.
			 */
			$allTransformations = $this->getTransformations()->retrieve($this->getTransformKey());

			/* Do it
			 */
			foreach ($allTransformations as $currentTransformation) {
				$this->transform($currentTransformation['method'], $currentTransformation['args']);
			}
		}

		return $this;
	}

	/**
	 * @param  string $transform_key
	 * @return $this
	 */
	public function setTransformKey($transform_key) {
		$this->transform_key = $transform_key;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTransformKey() {
		return $this->transform_key;
	}
}
