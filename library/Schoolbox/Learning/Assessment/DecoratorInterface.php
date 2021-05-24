<?php

/**
 * DecoratorInterface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

interface DecoratorInterface
{
    /**
     * Decorate an $assessment with type-specific info
     *
     * @param IAssessable $assessment
     */
    public function decorate($assessment);
}
