<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\L10n\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\L10n\Model;
use Schoolbox\L10n\RosettaStone;

class RosettaStoneTest extends TestCase
{
    /**
     * @var RosettaStone
     */
    private $stone;

    /**
     * Build a stone
     */
    public function setUp(): void
    {
        $this->stone = (new RosettaStone())
            ->addTerm($this->buildTerm('ca', 'Calendar', 'calendar', 'calendars', 'a', true))
            ->addTerm($this->buildTerm('in', 'Indicator', 'indicator', 'indicators', 'an', true))
            ->addTerm($this->buildTerm('pe', 'Person', 'person', 'people', 'a', false))
            ->addTerm($this->buildTerm('ev', 'Event', 'event', 'events', 'an', false))
        ;
    }

    /**
     * Data for translation test
     *
     * @return array
     */
    public static function getTestTranslateData()
    {
        return [
            // Calendar
            'calendar (singular)'       => ['ca', false, false, 'calendar'],
            'calendar (plural)'         => ['ca', true,  false, 'calendars'],
            'calendar (caps, singular)' => ['ca', false, true,  'Calendar'],
            'calendar (caps, plural)'   => ['ca', true,  true,  'Calendars'],

            // Indicator
            'indicator (singular)'       => ['in', false, false, 'indicator'],
            'indicator (plural)'         => ['in', true,  false, 'indicators'],
            'indicator (caps, singular)' => ['in', false, true,  'Indicator'],
            'indicator (caps, plural)'   => ['in', true,  true,  'Indicators'],

            // Person (no capitalize first)
            'person (singular)'       => ['pe', false, false, 'person'],
            'person (plural)'         => ['pe', true,  false, 'people'],
            'person (caps, singular)' => ['pe', false, true,  'person'],
            'person (caps, plural)'   => ['pe', true,  true,  'people'],

            // Event (no capitalize first)
            'event (singular)'       => ['ev', false, false, 'event'],
            'event (plural)'         => ['ev', true,  false, 'events'],
            'event (caps, singular)' => ['ev', false, true,  'event'],
            'event (caps, plural)'   => ['ev', true,  true,  'events'],
        ];
    }

    public static function getTestIndefiniteArticleData()
    {
        return [
            // Calendar
            'calendar (singular)' => ['ca', false, 'a'],
            'calendar (plural)'   => ['ca', true,  'A'],

            // Indicator
            'indicator (singular)' => ['in', false, 'an'],
            'indicator (plural)'   => ['in', true,  'An'],

            // Person (no capitalize first)
            'person (singular)' => ['pe', false, 'a'],
            'person (plural)'   => ['pe', true,  'A'],

            // Event (no capitalize first)
            'event (singular)' => ['ev', false, 'an'],
            'event (plural)'   => ['ev', true,  'An'],
        ];
    }

    /**
     * Data for translation test (when term is not known by stone)
     *
     * @return array
     */
    public static function getTestTranslateUnknownTermData()
    {
        return [
            'nothing' => ['nothing'],
            '1'       => [1],
            'null'    => [null],
        ];
    }

    /**
     * Data for sentence translation test
     *
     * @return array
     */
    public static function getTestTranslateSentenceData()
    {
        return [
            // Event is not capitalized...
            ['{:ev|c} is starting for {:pe|p}', 'event is starting for people'],
            // but its indefinite article ignores that rule
            ['{:ev|indef|c} {:ev} is starting for {:pe|p}', 'An event is starting for people'],
            ['{:ev|indef} {:ev} is starting for {:pe|p}', 'an event is starting for people'],
            // Order of filters doesn't matter
            ['{:in|c|p} for {:in|p|c}', 'Indicators for Indicators'],
            // Unknown terms get cast to empty string?
            ['{:ca} for {:unknown|p|c}', 'calendar for '],
            // No terms at all
            ['No terms at all', 'No terms at all'],
            // Unknown filter does nothing?
            ['Unknown filter "filter" on "ca": {:ca|filter}', 'Unknown filter "filter" on "ca": calendar'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateData')]
    public function testTranslate($term, $plural, $capital, $expected)
    {
        $this->assertEquals($expected, $this->stone->translate($term, $plural, $capital));
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateUnknownTermData')]
    public function testTranslateUnknownTerm($term)
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->stone->translate($term, false, false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIndefiniteArticleData')]
    public function testTranslateIndefiniteArticle($term, $plural, $expected)
    {
        $this->assertEquals($expected, $this->stone->indefiniteArticle($term, $plural));
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateUnknownTermData')]
    public function testTranslateIndefiniteArticleUnknownTerm($term)
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->stone->indefiniteArticle($term, false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateSentenceData')]
    public function testTranslateSentence($sentence, $expected)
    {
        $this->assertEquals($expected, $this->stone->translateSentence($sentence));
    }

    /**
     * Helper for building a term for testing
     *
     * @param string $term
     * @param string $title
     * @param string $singular
     * @param string $plural
     * @param string $indef
     * @param bool   $capitalizeFirst
     * @return Model
     */
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
