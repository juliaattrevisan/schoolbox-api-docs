<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Interceptor;


use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;

class InterceptorDispatchStrategy extends DefaultDispatchStrategy {

	/**
	 * Get the name of the strategy currently being used
	 *
	 * @return string
	 */
	public function getName() {
		return 'Intercept';
	}

}