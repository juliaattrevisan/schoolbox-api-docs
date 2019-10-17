<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

/**
 * Class IsConfigSetTo
 *
 * Finds out if the given config name equates to $value
 *
 * @package Alaress\Authorizer\Rule
 */
class IsConfigSetTo implements IAuthorizationRule
{

    /**
     * The array key in $params that contains the config name.
     *
     * @var string
     */
    protected $paramsKey = 'id';

    /**
     * The array key in $params that contains the config value
     * @var string
     */
    protected $paramsValue = '';

    /**
     * @var IConfigLoader
     */
    protected $configLoader;

    /**
     * Constructor (obviously)
     *
     * @param string        $paramsKey
     * @param               $paramsValue
     * @param IConfigLoader $configLoader
     */
    public function __construct($paramsKey, $paramsValue, IConfigLoader $configLoader)
    {
        $this->paramsKey = $paramsKey;
        $this->paramsValue = $paramsValue;
        $this->configLoader = $configLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($this->configLoader->get($this->paramsKey) == $this->paramsValue) {
            return true;
        }

        throw new Unauthorized();
    }
}
