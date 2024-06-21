<?php

declare(strict_types=1);

namespace External\Isams\Register\GroupType;

use Psr\SimpleCache\InvalidArgumentException;

interface GroupTypeInterface
{

    /**
     * Process the output array for group type data
     *
     * @param array $out
     * @return array
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     *
     */
    public function process(array $out): array;
}
