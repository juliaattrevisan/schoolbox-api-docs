<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_RecentVisitorsController extends Component_Homepage_Statistics_ControllerAbstract
{
    private $visitors;

    public function execute()
    {
        $dateFilter = '';
        if (count($this->dateRange)) {
            $dateFilter = sprintf(
                "AND datetime BETWEEN '%s' AND '%s'", date('Y-m-d', $this->dateRange[0]),
                date('Y-m-d', $this->dateRange[1])
            );
        }

        $groupFilter = '';
        if ($this->groupType != 'all') {
            $groupFilter = sprintf("AND g.group_type = '%s'", $this->groupType);
        }

        $stmt = Database::getInstance()->prepare(
            "
            SELECT `datetime`, u.id, u.synergy_id synid, u.title, u.firstname, u.lastname, g.name gname
            FROM homepage_log hl
            LEFT JOIN `user` u ON hl.user = u.id
            LEFT JOIN groups g ON u.group_id = g.id
            WHERE cid = :catID
            AND u.id IS NOT NULL
            $dateFilter
            $groupFilter
            ORDER BY `datetime` DESC"
        );
        $stmt->execute(array('catID' => $this->getContext()->getId()));

        $visitors = array();
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $visitors[] = $user;
        }
        $this->visitors = $visitors;
    }

    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'recentVisitors')) {
            return false;
        }

        $uHandler = new User();
        $this->setBlock($templateHandle, 'recent_visitor', 'recent_visitor_block');
        $this->setBlock($templateHandle, 'no_recent_visitor', 'no_recent_visitor_block');

        if (!count($this->visitors)) {
            $grp = $this->groupType == 'all' ? '' : $this->groupType;

            $this->setVar(array(
                'has_recent_visitor_block' => '',
                'recent_visitor_block' => '',
                'NO_VISITOR_GROUP_NAME' => $grp
            ));

            $this->parse('no_recent_visitor_block', 'no_recent_visitor');
        }
        else {
            for ($i = 0; $i < 8 && $i < count($this->visitors); ++$i) {
                $dtime = new Alaress_Date_Format($this->visitors[$i]['datetime'], 'Y-m-d H:i:s');
                $this->setVar(
                    array(
                        'VISITOR_ID' => $this->visitors[$i]['id'],
                        'VISITOR_THUMB' => sprintf('/portrait.php?userId=%s&size=square64', $this->visitors[$i]['synid']),
                        'VISITOR_NAME' => $uHandler->formatName($this->visitors[$i]),
                        'VISITOR_GROUP' => Scrub::text($this->visitors[$i]['gname']),
                        'VISITOR_TOOLTIP' => sprintf(
                            "%s (last visited %s)", $uHandler->formatName($this->visitors[$i]),
                            lcfirst($dtime->getRelativeTime())
                        )
                    )
                );
                $this->parse('recent_visitor_block', 'recent_visitor', true);
            }

            $this->parse('has_recent_visitor_block', 'has_recent_visitor');

            $this->setVar(array(
                'no_recent_visitor_block' => ''
            ));
        }

        return true;
    }

}
