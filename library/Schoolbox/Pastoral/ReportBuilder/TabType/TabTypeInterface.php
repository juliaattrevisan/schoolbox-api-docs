<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;

interface TabTypeInterface
{
    public function process(Collection $records, ?string $encodedCursor = null);
}
