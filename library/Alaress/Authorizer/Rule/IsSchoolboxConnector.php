<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Alaress\Authorizer\Rule;

use Schoolbox\IConfigLoader;
use Schoolbox\User;

/**
 * Class IsSchoolboxConnector
 *
 * Finds out if you're on the schoolbox connector
 *
 * @package Alaress\Authorizer\Rule
 */
class IsSchoolboxConnector implements IAuthorizationRule
{

    /**
     * @var IConfigLoader
     */
    protected $configLoader;

    /**
     * Constructor (obviously)
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null): bool
    {
        return (new IsConfigSetTo('external_type', 'schoolbox', $this->configLoader))->authorize($params, $user);
    }
}
