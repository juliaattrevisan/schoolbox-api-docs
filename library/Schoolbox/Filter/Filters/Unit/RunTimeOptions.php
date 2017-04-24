<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Unit;

use Schoolbox\Filter\AbstractRunTimeOptions;
use Schoolbox\User;

/**
 * For now, unit-spec lists are generated at run-time
 */
class RunTimeOptions extends AbstractRunTimeOptions
{

    /**
     * @var User $viewee
     */
    private $viewee;


    /**
     * @param User          $viewee
     * @param string[]|null $unitSpecKeys
     */
    public function __construct(User $viewee, array $unitSpecKeys = null)
    {
        parent::__construct($unitSpecKeys);
        $this->viewee = $viewee;
    }

    /* Getters
     */


    /**
     * @return User $viewee
     */
    public function getViewee()
    {
        return $this->viewee;
    }
}
