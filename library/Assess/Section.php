<?php
/**
 * Assess_Section
 *
 * All Sections are managed here.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Section extends Evaluation_Section {
    public $_table = 'assess_section';
    public $_index = 'section_id';

    /* A const to determine whether to include sections in getContents
     */
    const SECTION_EXCLUDE = true;
}
