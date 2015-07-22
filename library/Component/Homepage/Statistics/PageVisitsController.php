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

        // list of epoch days included in this
        $start = (int)($this->dateRange[0] / 86400);
        $end = (int)($this->dateRange[1] / 86400);

        // create a template array for populating into to account for zero days
        $data = array();
        foreach($groupColours as $groupname => $colour) {
            for($i = $start; $i <= $end; ++$i) {
                $data[$groupname][$i] = 0;
            }
        }

        $groupFilter = $this->groupType == 'all' ? '' : "AND group_type = '{$this->groupType}'";
        $dateFilter = "AND date BETWEEN '" . date('Y-m-d', $this->dateRange[0]) . "' AND '" . date('Y-m-d', $this->dateRange[1]) . "'";

        $stmt = Database::getInstance()->prepare("
            SELECT hv.date, SUM(hitcount) hcount, group_type
            FROM homepage_visits hv
            LEFT JOIN groups g ON hv.user_group_id = g.`id`
            WHERE category_id = :cid
            AND group_type IS NOT NULL
            $groupFilter
            $dateFilter
            GROUP BY `date`, group_type
            ORDER BY `date` ASC, group_type ASC;"
        );

        $stmt->execute(array('cid' => $this->getContext()->getId()));

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $adf = new Alaress_Date_Format($row['date'], 'Y-m-d');
            $time = (int)($adf->getUnixTimestamp() / 86400);
            $data[$row['group_type']][$time] = $row['hcount'];

            if(!isset($this->colours[$row['group_type']])) {
                $this->colours[$row['group_type']] = $groupColours[$row['group_type']];
            }
        }

        // sort or remove the series, depending on whether or not they were populated
        foreach($data as $group => &$d) {
            if(!isset($this->colours[$group])) {
                unset($data[$group]);
            }
            else {
                ksort($d);
            }
        }

        $colorList = array();
        foreach($data as $groupname => $series) {
            $groupdata = array();
            $k = array_keys($series);
            for($i = 0; $i < count($series); ++$i) {
                if($series[$k[$i]] || $series[$k[$i+1]] || $series[$k[$i-1]]) {
                    $groupdata[] = array(($k[$i]+1) * 86400000, (int)$series[$k[$i]]);
                }
            }

            $colorList[] = $this->colours[$groupname];
            $this->graphData[] = array(
                'name' => ucfirst($groupname),
                'data' => $groupdata
            );
        }
        $this->colours = $colorList;

		/* Always return something. If you don't, the graph won't render
		 * axes, labels, the major/minor lines etc. It'll just be blank.
		 * If you have no data to plot, just return an empty element.
		 * This forces the axes to render.
		 */
		if (!count($this->graphData)) {
			$this->graphData[] = array();
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
            'GRAPH_COLOURS' => json_encode($this->colours)
        ));

        return true;
    }

}
