<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar\Event\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Format;

class Renderer extends Handler
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'event';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Calendar';

    /**
     * Set the tab name to a localised format.
     */
    public function setup()
    {
        $this->setTabName($this->getPage()->getLocalisedTerm('calendar', false, true));
    }

    /**
     * Render
     */
    public function render()
    {
        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('event_t', 'resource/event/search/results.html');
            $this->getPage()->setBlock('event_t', 'event', 'event_block');
            $this->getPage()->setVar(array(
                'EVENT_DISP_COUNT' => count($this->getResult($this->getType())),
                'EVENT_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $event) {
                $date = new Alaress_Date_Format($event['start_datetime'], Alaress_Date_Format::DATABASE_FORMAT);
                $event_time = '';

                if (!empty($event['startTime']) && $event['startTime'] != '12:00AM') {
                    $event_time .= $event['startTime'];

                    if (!empty($event['endTime']) && $event['startTime'] != '12:00AM') {
                        $event_time .= ' to ' . $event['endTime'];
                    }
                }

                $this->getPage()->setVar(array(
                    'EVENT_TITLE' => Scrub::text($event['title']),
                    'EVENT_TYPE' => Scrub::text($event['eventType']),
                    'EVENT_DETAIL' => TextFormatter::cutSentence(Scrub::html2text($event['detail']), 150),
                    'EVENT_DATE' => $date->getDateOnly(),
                    'EVENT_DATE_RAW' => $date->getURLDate(),
                    'EVENT_LOCATION' => $event['location'],
                    'EVENT_TIME' => $event_time
                ));

                $this->getPage()->parse('event_block', 'event', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'event_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
