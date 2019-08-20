<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\MarkPerCap;

use function in_array;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

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
    public function getAllPossibleItems(): Collection
    {
        return new Collection(Definition::getStaticCustomKeys());
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if(in_array($key, Definition::getStaticCustomKeys(), true)){
            return;
        }
        throw new InvalidArgumentException("Key appears invalid: '$key'");
    }
}
