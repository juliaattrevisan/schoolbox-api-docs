<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\CapabilityIndicator;

use function in_array;
use InvalidArgumentException;
use function is_numeric;
use Schoolbox\Collection;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;
use function substr;

class KeyConverter extends AbstractKeyConverter
{

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
        $prefix = $key[0];
        if (!in_array($prefix, ['c', 'i'], true)) {
            throw new InvalidArgumentException("Key prefix is invalid: '$key'");
        }
        $id = substr($key, 1);
        if (!is_numeric($id)) {
            throw new InvalidArgumentException("Key identifier invalid: '$key'");
        }
    }
}
