<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\L10n\RosettaStone\Test;

use Schoolbox\L10n\Model;
use Schoolbox\L10n\RosettaStone;

class RosettaStoneTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RosettaStone
     */
    private $stone;

    /**
     * Build a stone
     */
    public function setup()
    {
        $this->stone = (new RosettaStone())
            ->addTerm($this->buildTerm('calendar', 'Calendar', 'calendar', 'calendars', 'a', true))
            ->addTerm($this->buildTerm('indicator', 'Indicator', 'indicator', 'indicators', 'an', true))
            ->addTerm($this->buildTerm('person', 'Person', 'person', 'people', 'a', false))
            ->addTerm($this->buildTerm('event', 'Event', 'event', 'events', 'an', false))
        ;
    }

    /**
     * Data for translation test
     *
     * @return array
     */
    public function getTestTranslateData()
    {
        return [
            // Calendar
            'calendar (singular)'       => ['calendar', false, false, 'calendar'],
            'calendar (plural)'         => ['calendar', true,  false, 'calendars'],
            'calendar (caps, singular)' => ['calendar', false, true,  'Calendar'],
            'calendar (caps, plural)'   => ['calendar', true,  true,  'Calendars'],

            // Indicator
            'indicator (singular)'       => ['indicator', false, false, 'indicator'],
            'indicator (plural)'         => ['indicator', true,  false, 'indicators'],
            'indicator (caps, singular)' => ['indicator', false, true,  'Indicator'],
            'indicator (caps, plural)'   => ['indicator', true,  true,  'Indicators'],

            // Person (no capitalize first)
            'person (singular)'       => ['person', false, false, 'person'],
            'person (plural)'         => ['person', true,  false, 'people'],
            'person (caps, singular)' => ['person', false, true,  'person'],
            'person (caps, plural)'   => ['person', true,  true,  'people'],

            // Event (no capitalize first)
            'event (singular)'       => ['event', false, false, 'event'],
            'event (plural)'         => ['event', true,  false, 'events'],
            'event (caps, singular)' => ['event', false, true,  'event'],
            'event (caps, plural)'   => ['event', true,  true,  'events'],
        ];
    }

    public function getTestIndefiniteArticleData()
    {
        return [
            // Calendar
            'calendar (singular)' => ['calendar', false, 'a'],
            'calendar (plural)'   => ['calendar', true,  'A'],

            // Indicator
            'indicator (singular)' => ['indicator', false, 'an'],
            'indicator (plural)'   => ['indicator', true,  'An'],

            // Person (no capitalize first)
            'person (singular)' => ['person', false, 'a'],
            'person (plural)'   => ['person', true,  'A'],

            // Event (no capitalize first)
            'event (singular)' => ['event', false, 'an'],
            'event (plural)'   => ['event', true,  'An'],
        ];
    }

    /**
     * Data for translation test (when term is not known by stone)
     *
     * @return array
     */
    public function getTestTranslateUnknownTermData()
    {
        return [
            'nothing' => ['nothing'],
            '1'       => [1],
            'null'    => [null],
        ];
    }

    /**
     * @dataProvider getTestTranslateData
     */
    public function testTranslate($term, $plural, $capital, $expected)
    {
        $this->assertEquals($expected, $this->stone->translate($term, $plural, $capital));
    }

    /**
     * @dataProvider getTestTranslateUnknownTermData
     * @expectedException \OutOfBoundsException
     */
    public function testTranslateUnknownTerm($term)
    {
        $this->stone->translate($term, false, false);
    }

    /**
     * @dataProvider getTestIndefiniteArticleData
     */
    public function testTranslateIndefiniteArticle($term, $plural, $expected)
    {
        $this->assertEquals($expected, $this->stone->indefiniteArticle($term, $plural));
    }

    /**
     * @dataProvider getTestTranslateUnknownTermData
     * @expectedException \OutOfBoundsException
     */
    public function testTranslateIndefiniteArticleUnknownTerm($term)
    {
        $this->stone->indefiniteArticle($term, false);
    }

    public function testTranslateSentence()
    {
        //
    }

    private function buildTerm($term, $title, $singular, $plural, $indef, $capitalizeFirst)
    {
        return (new Model())
            ->setTerm($term)
            ->setTitle($title)
            ->setSingularForm($singular)
            ->setPluralForm($plural)
            ->setIndefiniteArticle($indef)
            ->setCapitaliseFirst($capitalizeFirst)
        ;
    }
}
