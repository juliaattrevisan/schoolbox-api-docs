<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\MarkPerCap;

use function in_array;
use InvalidArgumentException;
use function preg_match;
use Schoolbox\Collection;
use Schoolbox\Filter;
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
        if(in_array($key, Definition::getStaticCustomKeys(), true)){
            return;
        }
        throw new InvalidArgumentException("Key appears invalid: '$key'");
    }
}
