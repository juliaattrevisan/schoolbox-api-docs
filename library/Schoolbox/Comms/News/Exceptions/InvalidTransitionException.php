<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Exceptions;

use DomainException;
use Schoolbox\Comms\News\Model;
use Throwable;

/**
 * Thrown when caller requests the model to perform a state transition that is invalid.
 * eg rejecting of a article that is not awaiting moderation, or submitting an already submitted article
 *
 * Callers may or may not need to inform the user.
 * ie the UI should not indicate that the request was successful.
 * It may be appropriate to warn the user 'article X is currently stateY', but that's up to the controller.
 *
 * Note that this is purely for state-transitions, it has nothing to do with whether some user can do something,
 * that's for module auth rules to determine.
 */
class InvalidTransitionException extends DomainException
{

    /**
     * @var int
     * @see Model::STATE_* constants
     */
    private $currentState;


    /**
     * @param int            $state
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(int $state, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->currentState = $state;
        parent::__construct($message, $code, $previous);
    }


    /**
     * Returns the current state to enable the catcher to determine whether they can ignore this exception.
     * eg, we may not care that we are trying to submit an already submitted article and can just carry on
     * @return int
     */
    public function getCurrentState(): int
    {
        return $this->currentState;
    }
}
