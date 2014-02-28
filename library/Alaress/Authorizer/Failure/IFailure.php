<?php
/**
 * Interface for authorization rules (for plugging into Alaress\Authorizer\Authorizer)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Alaress\Authorizer\Failure;

interface IFailure {
    public function onFailure();
}
