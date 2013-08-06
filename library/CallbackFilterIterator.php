<?php
/**
 * PHP 5.3 implementation of CallbackFilterIterator
 *
 * @see http://www.php.net/manual/en/class.callbackfilteriterator.php#108803
 */

if (!class_exists('CallbackFilterIterator')) {
	class CallbackFilterIterator extends FilterIterator {
		protected $callback;

		public function __construct(Iterator $iterator, $callback = null) {
			$this->callback = $callback;
			parent::__construct($iterator);
		}

		public function accept() {
			return call_user_func(
				$this->callback,
				$this->current(),
				$this->key(),
				$this->getInnerIterator()
			);
		}
	}
}
