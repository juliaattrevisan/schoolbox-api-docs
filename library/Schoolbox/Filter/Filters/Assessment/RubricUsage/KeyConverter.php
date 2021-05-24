<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\RubricUsage;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;
use UnexpectedValueException;

class KeyConverter extends AbstractKeyConverter
{
    /** @var Codec */
    private $codec;


    /**
     * @param Filter\Model $filter
     * @param Codec        $codec
     */
    public function __construct(Filter\Model $filter, Codec $codec)
    {
        parent::__construct($filter);
        $this->codec = $codec;
    }


    /**
     * @inheritdoc
     * @return string
     */
    protected function getItem(string $key, User $user, string $page): string
    {
        $this->assertValidKey($key, $user, $page);
        return $key;
    }


    /**
     * @inheritDoc
     */
    public function possibleItemCount(): int
    {
        // TODO, for now just assuming the selections will never represent all capabilities and indicators in the system
        return 0;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if ($key === Definition::KEY_NONE) {
            return;
        }
        try {
            $this->codec->decode($key);
        } catch (UnexpectedValueException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
