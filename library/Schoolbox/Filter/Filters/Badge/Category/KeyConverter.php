<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);


namespace Schoolbox\Filter\Filters\Badge\Category;


use Schoolbox\Filter\AbstractKeyConverter;


class KeyConverter extends AbstractKeyConverter
{

    protected function getItem(string $key,\Schoolbox\User $user,string $page)
    {
        // TODO: Implement getItem() method.
    }


    protected function assertValidKey(string $key,\Schoolbox\User $user,string $page) : void
    {
        // TODO: Implement assertValidKey() method.
    }


    public function possibleItemCount() : int
    {
        // TODO: Implement possibleItemCount() method.
    }
}
