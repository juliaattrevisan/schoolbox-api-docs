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
     * Dependency Injector
     *
     * @var Di\Container
     */
    private $di;

    /**
     * HtmlFormatter constructor.
     *
     * @param Di\Container $di
     */
    public function __construct(Di\Container $di)
    {
        $this->di = $di;
    }


    /**
     * Manufacture an nice html version of the data tuples
     * Augment the original data set with this html content
     *
     * $data is an array of (id, name, entityType, modifier) tuples
     * @param array $data
     * @return array
     * @throws Di\Exception\ServiceNotFound
     */
    public function decorate(array $data): array
    {
        $output = $this->di->get('webOutput:nochrome');

        foreach ($data as $key => &$current) {
            $current['itemHtml'] = $output->setTemplate('@commsNews/audienceItem.twig')->setData($current)->getAsString();
        }

        unset($current);

        return $data;
    }
}