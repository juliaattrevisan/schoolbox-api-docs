<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Quiz\Attempt;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * Update the details of an attempt
     *
     * @param QuizDomainObject $assessment
     * @param User             $student
     * @param Model            $attempt
     * @return void
     */
    public function getModify(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void
    {
        /* TODO build attempt editor form here, which submits to postModify()
         */
    }


    /**
     * Update the details of an attempt by superimposing a POST array's values
     */
    public function postModify(): void
    {
        print_r($this->requestPost->getArrayCopy());
        new \Alaress_Probe('post modify');
    }
}
