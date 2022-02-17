<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Calendar\Event\Collection;

use Calendar\Event\Type\EventBase;

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

        $events = $renderer->getAllEvents();
        echo '[';
        foreach ($events as $i => $event) {
            echo ($i > 0 ? ',' : '') . $renderer->renderEvent($event, EventBase::RENDER_FORMAT_JSON);
        }
        echo ']';
    }
}
