<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_UnvisitedController extends Component_Homepage_Statistics_ControllerAbstract
{
    private $unvisitors;

    public function execute()
    {
        $this->unvisitors = array();

        $dateFilter = '';
        if (count($this->dateRange)) {
            $dateFilter = sprintf(
                "AND datetime BETWEEN '%s' AND NOW()", date('Y-m-d', $this->dateRange[0])
            );
        }

        $groupFilter = '';
        if ($this->groupType != 'all') {
            $groupFilter = sprintf("AND g.group_type = '%s'", $this->groupType);
        }

        // is this a class? if it has a class code, then yes
        if ($code = $this->getContext()->getCodeString()) {
            $synergeticClass = new External_Class;
            $classList = $synergeticClass->getStudents($code);

            if (is_array($classList) && count($classList)) {

                // an iterated prepared statement is better than dozens of single queries
                $stmt = Database::getInstance()->prepare(
                    "SELECT u.id, u.title, g.name gname
                     FROM `user` u
                     LEFT JOIN groups g ON u.group_id = g.id
                     WHERE synergy_id = :sid
                     $groupFilter
                     AND u.id NOT IN (
                       SELECT u.id
                       FROM homepage_log hl
                       LEFT JOIN `user` u ON hl.user = u.id
                       WHERE hl.cid = :cid
                       $dateFilter
                       GROUP BY `user`
                     )"
                );

                foreach ($classList as $student) {
                    $stmt->execute(
                        array(
                            'sid' => $student['synergy_id'],
                            'cid' => $this->getContext()->getId()
                        )
                    );
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row['id']) {
                        continue;
                    }
                    $student['id'] = $row['id'];
                    $student['title'] = $row['title'];
                    $student['gname'] = $row['gname'];
                    $this->unvisitors[] = $student;
                }
            }
            return true;
        }

        // else is this a group?
        $stmt = Database::getInstance()->prepare("SELECT cid FROM userGroups WHERE cid = :id");
        $stmt->execute(array('id' => $this->getContext()->getId()));
        if ($stmt->rowCount() == 1) {
            $stmt = Database::getInstance()->prepare(
                "SELECT
                 u.id, u.title, u.firstname, u.lastname, u.synergy_id, g.name gname
             FROM category c
             RIGHT JOIN userGroups ug ON ug.cid = c.id
             LEFT JOIN userAccess ua ON ua.sid = c.id AND service = '1'
             LEFT JOIN `user` u ON u.id = ua.uid
             LEFT JOIN groups g ON u.group_id = g.id
             WHERE c.id = :cid
             $groupFilter
             AND u.id NOT IN (
                 SELECT `user`
                 FROM homepage_log hl
                 WHERE cid = :cid
                 $dateFilter
                 GROUP BY `user`
             )"
            );

            $stmt->execute(array('cid' => $this->getContext()->getId()));

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->unvisitors[] = $row;
            }
            return true;
        }

        // if neither of the above 2 conditions are met, then this is an open homepage with no formal membership
        // therefore there non-visited block is irrelevant and to be hidden
        return false;

    }

    /**
	 * Render template
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'unvisited')) {
            return false;
        }

        /* Don't want to convert all this stuff to mappers right now.
		 */
        $userHandler = new User();

        $this->setBlock($templateHandle, 'unvisitor', 'unvisitor_block');

        if (count($this->unvisitors)) {
            for ($i = 0; $i < 8 && $i < count($this->unvisitors); ++$i) {
                $this->setVar(array(
                    'VISITOR_ID' => $this->unvisitors[$i]['id'],
                    'VISITOR_THUMB' => "/portrait.php?userId={$this->unvisitors[$i]['synergy_id']}&size=square64",
                    'VISITOR_NAME' => $userHandler->formatName($this->unvisitors[$i]),
                    'VISITOR_GROUP' => Scrub::text($this->unvisitors[$i]['gname']),
                    'VISITOR_TOOLTIP' => $userHandler->formatName($this->unvisitors[$i])
                ));

                $this->parse('unvisitor_block', 'unvisitor', true);
            }
        } else {
            $grp = $this->groupType == 'all' ? '' : $this->groupType;
            $this->setVar('unvisitor_block', "<p>All $grp members have visited in this time period.</p>");
        }

        return true;
    }

}
