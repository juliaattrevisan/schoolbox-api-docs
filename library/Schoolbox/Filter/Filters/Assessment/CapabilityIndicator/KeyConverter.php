<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\CapabilityIndicator;

use InvalidArgumentException;
use Schoolbox\Collection;
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
     * @return Collection|string[]
     */
    public function getAllPossibleItems()
    {
        // TODO, for now just assuming the selections will never represent all capabilities and indicators in the system
        return new Collection();
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
