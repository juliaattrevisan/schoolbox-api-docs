<?php
/**
 * HtmlFormatter
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Aura\Di;

class HtmlFormatter
{
    /**
     * An array of (id, name, entityType, modifier) tuples
     * @var array
     */
    private $data;

    /**
     * Dependency Injector
     *
     * @var Di\Container
     */
    private $di;

    /**
     * HtmlFormatter constructor.
     *
     * @param Di\Container $di
     * @param array        $data
     */
    public function __construct(Di\Container $di, array $data)
    {
        $this->data = $data;
        $this->di = $di;
    }


    /**
     * Manufacture an nice html version of the data tuples
     * Augment the original data set with this html content
     */
    public function decorate(): array
    {
        $output = $this->di->get('webOutput:nochrome');

        foreach ($this->data as &$current) {
            $current['itemHtml'] = $output->setTemplate('@commsNews/audienceItem.twig')->setData($current)->getAsString();
        }

        unset($current);

        return $this->data;
    }
}