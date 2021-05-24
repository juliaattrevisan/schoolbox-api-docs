<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use DebugBar\DebugBar as DebugBarInstance;

/**
 * Generate data for rendering the debug bar.
 *
 * This should only be added if the debug bar is actually enabled.
 */
class DebugBar implements IDataGenerator
{
    /**
     * @var DebugBarInstance
     */
    private $debugBar;


    /**
     * @var string
     */
    private $baseUrl;


    /**
     * Constructor
     *
     * @param DebugBarInstance $debugBar
     * @param string           $baseUrl
     */
    public function __construct(DebugBarInstance $debugBar, string $baseUrl)
    {
        $this->debugBar = $debugBar;
        $this->baseUrl  = $baseUrl;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $renderer = $this->debugBar->getJavascriptRenderer();
        $renderer->setBaseUrl($this->baseUrl);
        return [
            'head' => $renderer->renderHead(),
            'body' => $renderer->render(),
        ];
    }
}
