<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\IStrategy;
use UnexpectedValueException;

/**
 * Common logic and safe-defaults for Audience segment strategies
 */
abstract class Base implements IStrategy
{

    public const ENTITY_TYPE = '';  // concrete classes must override

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @return string
     */
    public function getEntityType(): string
    {
        if(!static::ENTITY_TYPE){
            /** @noinspection PhpUnhandledExceptionInspection */
            throw new UnexpectedValueException('Concrete classes must implement.');
        }
        return static::ENTITY_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // Default implementation. Assume 1:1 with users, eg not possible for a user to not have a Role.
        return false;
    }
}
