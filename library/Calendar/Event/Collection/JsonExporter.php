<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Calendar\Event\Collection;

class JsonExporter implements IExporter
{

    /**
     * @param Renderer $renderer
     * @param string   $filename
     * @throws \Exception
     */
    public function export(Renderer $renderer, string $filename): void
    {
        header("Cache-Control: private");
        header("Content-Type: application/json; charset=utf-8");
        header("Content-Disposition: attachment; filename={$filename}.json");

        printf("[%s]", \implode(',', $renderer->renderAll('javascript')));
    }
}
