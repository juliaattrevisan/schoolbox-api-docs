<?php
/**
 * Finds out if the given config name equates to (bool)true
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class IsConfigOn implements IAuthorizationRule
{
    /**
     * The array key in $params that contains the user ID.
     *
     * @var string
     */
    protected $paramsKey = 'id';

    /**
     * @var IConfigLoader
     */
    protected $configLoader;

    /**
     * Constructor (obviously)
     *
     * @param string $paramsKey
     * @param IConfigLoader $configLoader
     */
    public function __construct($paramsKey, IConfigLoader $configLoader)
    {
        $this->paramsKey = $paramsKey;
        $this->configLoader = $configLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($this->configLoader->get($this->paramsKey)) {
            return true;
        }

        throw new Unauthorized();
    }
}
