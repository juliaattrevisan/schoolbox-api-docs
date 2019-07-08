<?php
/**
 * DecoratorInterface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
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
