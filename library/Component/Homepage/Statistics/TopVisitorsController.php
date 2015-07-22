<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_TopVisitorsController extends Component_Homepage_Statistics_ControllerAbstract
{
    private $visitors;

    public function execute()
    {
        $visitors = array();

        $dateFilter = '';
        if (count($this->dateRange)) {
            $dateFilter = sprintf(
                "AND datetime BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                date('Y-m-d', $this->dateRange[0]),
                date('Y-m-d', $this->dateRange[1])
            );
        }

        $groupFilter = '';
        if ($this->groupType != 'all') {
            $groupFilter = sprintf("AND g.group_type = '%s'", $this->groupType);
        }

        $stmt = Database::getInstance()->prepare(
            "
            SELECT u.id, u.synergy_id synid, u.title, u.firstname, u.lastname, g.name group_name, SUM(hitcount) AS hits
            FROM homepage_log hl
            LEFT JOIN `user` u ON hl.user = u.id
            LEFT JOIN groups g ON u.group_id = g.id
            WHERE cid = :cat
            $dateFilter
            $groupFilter
            GROUP BY `user`
            ORDER BY hits DESC"
        );
        $stmt->execute(array('cat' => $this->getContext()->getId()));

        $i = 0;
        while ($visitor = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($visitor['hits'] > 0) {
                $visitors[] = $visitor;
                $visitors[$i]['proportion'] = $visitor['hits'] / $visitors[0]['hits'];
                $visitors[$i]['hits'] = $visitor['hits'];

                $i++;
            }
        }

        $this->visitors = $visitors;

    }


    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'topVisitors')) {
            return false;
        }

        $this->setBlock($templateHandle, 'top_visitor', 'top_visitor_block');
        $this->setBlock($templateHandle, 'no_top_visitor', 'no_top_visitor_block');

        $uHandler = new User();

        if (!count($this->visitors)) {
            $grp = $this->groupType == 'all' ? '' : $this->groupType;

            $this->setVar(array(
                'top_visitor_block' => '',
                'NO_TOP_VISITOR_GROUP_NAME' => $grp,
            ));

            $this->parse('no_top_visitor_block', 'no_top_visitor');
        }
        else {
            for ($i = 0; $i < 5 && $i < count($this->visitors); ++$i) {
                $this->setVar(
                    array(
                        'VISITOR_ID' => $this->visitors[$i]['id'],
                        'VISITOR_THUMB' => sprintf("/portrait.php?userId=%s&size=square64", $this->visitors[$i]['synid']),
                        'VISITOR_NAME' => $uHandler->formatName($this->visitors[$i]),
                        'VISITOR_GROUP' => Scrub::text($this->visitors[$i]['group_name']),
                        'VISITOR_PROGRESS' => (int)($this->visitors[$i]['proportion'] * 100),
                        'VISITOR_HITS' => $this->visitors[$i]['hits'],
                    )
                );
                $this->parse('top_visitor_block', 'top_visitor', true);
            }

            $this->setVar(array(
                'no_top_visitor_block' => ''
            ));
        }

        return true;
    }
}
