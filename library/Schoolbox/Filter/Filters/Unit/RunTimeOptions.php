<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Unit;

use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\User;

/**
 * For now, unit-spec lists are generated at run-time
 */
class RunTimeOptions implements IRunTimeOptions
{
    /**
     * @var User $viewee
     */
    private $viewee;

    /**
     * @param User $viewee
     */
    public function __construct(User $viewee)
    {
        $this->viewee = $viewee;
    }


    /**
     * @return User $viewee
     */
    public function getViewee()
    {
        return $this->viewee;
    }
}
