<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Calendar\Event\Collection;


interface IExporter
{

    public function renderAll(Renderer $renderer, string $filename): void;
}
