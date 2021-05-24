<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Calendar\Event\Collection;

interface IExporter
{

    public function export(Renderer $renderer, string $filename): void;
}
