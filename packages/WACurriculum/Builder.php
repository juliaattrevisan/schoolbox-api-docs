<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace WACurriculum;

/**
 * Builds the WA Curriculum csv from a set of endpoints
 */
class Builder
{
    /**
     * Manifest is a hardcoded set of urls to json files that inform
     * the WA curriculum csv nodes. We can't guarantee how this set
     * changes over time
     *
     * The source website produces broken links, so the http protocol
     * is manually repaired to https:// here
     */
    private const MANIFEST = [
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/english.json' => 'English Curriculum - Pre-primary to Year 10 ',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/hpe.json' => 'Health and Physical Education Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/hass.json' => 'Humanities and Social Sciences Curriculum - Pre-primary to Year 10',

        /* Languages Curriculum
         */
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/chinese-second-language.json' => 'Chinese: Second Language Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/french-second-language.json' => 'French: Second Language Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/german-second-language.json' => 'German: Second Language Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/indonesian-second-language.json' => 'Indonesian: Second Language Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/italian-second-language.json' => 'Italian: Second Language Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/japanese-second-language.json' => 'Japanese: Second Language Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/languages7-10.json' => 'Languages Curriculum - Year 7 to Year 10',

        /* Some other unnamed section/grouping
         */
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/mathematics.json' => 'Mathematics Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/science.json' => 'Science Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/technologies.json' => 'Technologies Curriculum - Pre-primary to Year 10',
        'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON/arts.json' => 'The Arts Curriculum - Pre-primary to Year 10',
    ];


    public function generate()
    {

    }
}