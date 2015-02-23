<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_NavigatorController extends Component_Homepage_Statistics_ControllerAbstract
{

    private $rating;
    private $hits;

    const STAR_COUNT = 5;

    public function execute()
    {
        $this->calculateStarRating();
    }

    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'navigator')) {
            return false;
        }

        // parse stars
        $stars = $this->rating * self::STAR_COUNT;
        $this->setBlock($templateHandle, 'star', 'star_block');
        for ($i = 0; $i < self::STAR_COUNT; ++$i) {
            $this->parse('star_block', 'star', true);
        }
        $this->setVar('STAR_WIDTH', (int)($this->rating * 100));
        $this->setVar('RATING', sprintf('%0.1f / %0.1f (%s hits)', $stars, self::STAR_COUNT, $this->hits));

        // parse time-period navigator
        // 100 days
        $this->setBlock($templateHandle, 'date_start_option', 'date_start_option_block');
        $this->setBlock($templateHandle, 'date_end_option', 'date_end_option_block');

        $dt = new DateTime('midnight');

        // for one reason or another, an extra day needs to be added here
        $startDT = DateTime::createFromFormat('U', $this->dateRange[0] + (3600 * 24));
        $endDT = DateTime::createFromFormat('U', $this->dateRange[1]);

        for ($i = 0; $i < 12; ++$i) {
            $this->setVar(array(
                'DATE_START_SELECTED' => $dt->format('Ym') == $startDT->format('Ym') ? ' selected="selected"' : '',
                'DATE_START_VALUE'    => $dt->format('Y-m'),
                'DATE_START_NAME'     => $dt->format('M, Y')
            ));

            $this->setVar(array(
                'DATE_END_SELECTED' => $dt->format('Ym') == $endDT->format('Ym') ? ' selected="selected"' : '',
                'DATE_END_VALUE'    => $dt->format('Y-m'),
                'DATE_END_NAME'     => $dt->format('M, Y')
            ));

            $this->parse('date_start_option_block', 'date_start_option', true);
            $this->parse('date_end_option_block', 'date_end_option', true);

            $dt->add(DateInterval::createFromDateString('-1 month'));
        }


        // parse dropdown
        $this->setBlock($templateHandle, 'drop_option', 'drop_option_block');

        $result = Database::getInstance()->query("SELECT DISTINCT group_type t FROM groups");

        $groupTypes = $result->fetchAll(PDO::FETCH_ASSOC);
        array_unshift($groupTypes, array('t' => 'all'));

        foreach ($groupTypes as $group) {
            $this->setVar(array(
                'DROP_SELECTED' => $this->groupType == $group['t'] ? 'selected="selected"' : '',
                'DROP_VALUE'    => $group['t'],
                'DROP_NAME'     => ucfirst($group['t'])
            ));

            $this->parse('drop_option_block', 'drop_option', true);
        }

        return true;
    }




    /**
     * THIS METHOD CAN BE REPLACED WITH SOMETHING MORE REASONABLE
     * ONLY CONDITION IS THAT AT THE END IT SETS $this->rating AND $this->hits
     * FOR THE RATING TO BE MEANINGFUL, THIS METHOD SHOULD CONTAIN LOGIC THAT ACCOUNTS FOR UNIQUE-VISITS-PER-TIME-PERIOD
     * AS WELL AS PER-CAPITA (GROUP SIZE) WEIGHTINGS (but for now, it doesn't)
     */
    private function calculateStarRating()
    {
        $categoryID = $this->getContext()->getId();

        $inner = "
            SELECT cid, LOG(2, SUM(hitcount)) AS hits, SUM(hitcount) AS raw_hits
            FROM homepage_log hl
            RIGHT JOIN category c ON hl.`cid` = c.id
            GROUP BY cid
            ORDER BY hits DESC";

        $outer = "
            SELECT cid, raw_hits, ((hits-mean)/dev) AS norm
            FROM ($inner) AS t1,
            (
                SELECT AVG(hits) mean, STDDEV_POP(hits) dev
                FROM ($inner) AS t0
            )
            AS t2";

        $sqlA = $outer . " WHERE cid = $categoryID";
        $sqlB = "SELECT MAX(norm) `max`, MIN(norm) `min` FROM ($outer)AS t3";

        $a = Database::getInstance()->query($sqlA)->fetch(PDO::FETCH_ASSOC);
        $b = Database::getInstance()->query($sqlB)->fetch(PDO::FETCH_ASSOC);

        // account for the null case of 0 hits
        if ($a['norm'] === null) {
            $this->rating = 0;
        } else {
            $this->rating = ($a['norm'] - $b['min']) / ($b['max'] - $b['min']);
        }

        $this->hits = $a['raw_hits'];
    }
}
