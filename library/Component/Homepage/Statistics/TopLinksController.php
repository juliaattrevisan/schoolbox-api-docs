<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_TopLinksController extends Component_Homepage_Statistics_ControllerAbstract
{

    private $links;

    public function execute()
    {

        $dateFilter = '';
        if (count($this->dateRange)) {
            $dateFilter = sprintf(
                "AND time BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                date('Y-m-d', $this->dateRange[0]),
                date('Y-m-d', $this->dateRange[1])
            );
        }

        $stmt = Database::getInstance()->prepare(
            "
            SELECT l.id, l.url, COUNT(l.id) AS `count` FROM cmsLog cl
            LEFT JOIN link l ON cl.`objectId` = l.id AND cl.`object` = 'link'
            WHERE l.id IS NOT NULL
            AND l.`category` = :cid
            $dateFilter
            GROUP BY l.id
            ORDER BY `count` DESC"
        );

        $stmt->execute(array('cid' => $this->getContext()->getId()));

        $i = 0;

        while ($link = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->links[$i] = $link;
            $this->links[$i]['progress'] = (int)$this->links[$i]['count'] / $this->links[0]['count'] * 100;
            $this->links[$i]['hits'] = (int)$this->links[$i]['count'];

            $i++;
        }

        return true;
    }

    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'topLinks')) {
            return false;
        }

        $this->setBlock($templateHandle, 'top_link', 'top_link_block');
        $this->setBlock($templateHandle, 'no_top_link', 'no_top_link_block');

        if (count($this->links)) {
            for ($i = 0; $i < 5 && $i < count($this->links); ++$i) {
                $this->setVar(
                    array(
                        'LINK_ID' => $this->links[$i]['id'],
                        'LINK_THUMB' => '/images/icons/60x60/link.png',
                        'LINK_NAME' => $this->links[$i]['url'],
                        'LINK_PROGRESS' => $this->links[$i]['progress'],
                        'LINK_HITS' => $this->links[$i]['hits'],
                    )
                );
                $this->parse('top_link_block', 'top_link', true);
            }

            $this->setVar(array(
                'no_top_link_block' => ''
            ));
        } else {
            $this->setVar(array(
                'top_link_block' => ''
            ));

            $this->parse('no_top_link_block', 'no_top_link');
        }

        return true;
    }

}
