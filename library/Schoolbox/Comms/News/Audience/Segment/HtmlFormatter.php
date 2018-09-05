<?php
/**
 * HtmlFormatter
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Web\Output\Html;

class HtmlFormatter
{
    /**
     * A di-obtained output formatter class
     *
     * @var Html
     */
    private $outputter;


    /**
     * HtmlFormatter constructor.
     *
     * @param Html $outputter
     */
    public function __construct(Html $outputter)
    {
        $this->outputter = $outputter;
    }


    /**
     * Manufacture an nice html version of the data tuples
     * Augment the original data set with this html content
     *
     * $data is an array of (id, name, entityType, modifier) tuples
     * @param array $data
     * @return array
     */
    public function decorate(array $data): array
    {
        foreach ($data as $key => $current) {
            $data[$key]['itemHtml'] = $this->outputter->setTemplate('@commsNews/audienceItem.twig')->setData($current)->getAsString();
        }
        return $data;
    }
}
