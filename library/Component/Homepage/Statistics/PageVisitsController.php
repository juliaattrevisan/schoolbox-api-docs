<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_PageVisitsController extends Component_Homepage_Statistics_ControllerAbstract {

    private $graphData = array();

    private $colours = array();

    public function execute() {

        $groupColours = array(
            'staff'   => '#579DCF',
            'student' => '#E99D12',
            'parent'  => '#3DB114',
            'guest'   => '#762AAE'
        );

        $groupFilter = $this->groupType == 'all' ? '' : "AND group_type = '{$this->groupType}'";

        $stmt = Database::getInstance()->prepare("
            SELECT hv.date, SUM(hitcount) hcount, group_type
            FROM homepage_visits hv
            LEFT JOIN groups g ON hv.user_group_id = g.`id`
            WHERE category_id = :cid
            $groupFilter
            GROUP BY `date`, group_type
            ORDER BY `date` ASC, group_type ASC;"
        );

        $stmt->execute(array('cid' => $this->getContext('id')));

        $data = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $data[$row['group_type']][] = array(strtotime($row['date'])*1000, $row['hcount']);

            if(!isset($this->colours[$row['group_type']])) {
                $this->colours[$row['group_type']] = $groupColours[$row['group_type']];
            }
        }

        foreach($data as $groupname => $series) {
            $this->graphData[] = array(
                'label' => ucfirst($groupname),
                'data' => $series
            );
        }

        return true;

    }

    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'pageVisits')) {
            return false;
        }

        $this->setVar(array(
            'GRAPH_XMIN'    => $this->dateRange[0] * 1000,
            'GRAPH_XMAX'    => $this->dateRange[1] * 1000,
            'GRAPH_JSON'    => json_encode($this->graphData),
            'GRAPH_COLOURS' => json_encode(array_values($this->colours))
        ));

        return true;
    }

}
