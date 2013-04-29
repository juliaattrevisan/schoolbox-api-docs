<?php

/* * **********************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 * ********************************************************** */


/* Class defines interface between the menu database and the administration software.   *
 * The following actions can be performed; delete, add, update, return 			 */

class sideMenu extends DeprecatedModel {

    function __construct() {

        $this->_table = "sideMenu";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

    function getTree() {
        $query = "SELECT id, parent, txt, url, target FROM sideMenu ORDER BY parent, sequence, txt";
        $this->db->query($query);

        //initialise root of tree
        $tree[0] = array(
            'id'  => 0,
            'txt' => 'Top Level'
        );

        //fetch each record and insert into tree
        while($menuitem = $this->db->getNextArray() ) {
            $tree = $this->buildTree($tree, $menuitem);
        }

        return $tree;
    }

    function buildTree($list, $element) {
        for ($i = 0; $list[$i]; $i++) {
            //if item is parent insert item
            if ($list[$i]['id'] == $element['parent']) {
                $list[$i]['children'][] = $element;
                return $list;
            }

            if (is_array($list[$i]['children'])) {
                $sublist = $this->buildTree($list[$i]['children'], $element);
                $list[$i]['children'] = $sublist;
            }
        }
        return $list;
    }

    function outputTree($list, $level=0, $selectable = false)
    {
            $text = "<ul>\n";

            for($i=0; $list[$i]; $i++)
            {
                    if($list[$i]['target'])
                            $target = " target={$list[$i]['target']}";
                    else $target = '';

                    $text .= '<li id="node_'.$list[$i]['id'].'">';

                    if($selectable)
                        $text .= '<a id="' . $list[$i]['id'] . '" href="#">' . $list[$i]['txt'] . "</a>";
                    else
                        $text .= '<a href="/adminv2/modify-side-menu/'.$list[$i]['id'].'">'.$list[$i]['txt'].'</a>';

                    if(is_array($list[$i]['children'])) {
                            $text .= $this->outputTree($list[$i]['children'], ($level + 1), $selectable);
                    }

                    $text .= "</li>\n";
            }
            $text .= "</ul>\n";
            return $text;
    }

    function create($item) {

        $campus = $item['campus'];
        $group = $item['group'];
        unset($item['campus']);
        unset($item['group']);
        $item['sequence'] = $this->getLastItemSequence($item['parent']);
        $id = parent::create($item);
        $this->addPermissions($id, $campus, $group);
    }

    function getLastItemSequence($parent) {
        $query = "SELECT sequence FROM sideMenu WHERE parent = $parent ORDER BY sequence DESC";

        return $this->db->getOne($query) + 1;
    }

    function update($item) {
        $this->deletePermissions($item['id']);
        $this->addPermissions($item['id'], $item['campus'], $item['group']);
        unset($item['campus'], $item['group']);
        parent::update($item);

        $itemCur = $this->getByID($item['id']);
        $this->resequenceMenu($itemCur['parent']);
    }

    function moveItemAfter($id, $pid) {
        /* Place item after $pid in sequence */
        $item = $this->getByID($pid);

        $query = "UPDATE sideMenu SET sequence = (sequence + 1) WHERE parent = {$item['parent']} AND sequence > ({$item['sequence']} )";
        $this->db->query($query);

        parent::update(array('id'=>$id, 'sequence'=>$item['sequence']+1, 'parent'=>$item['parent']));
    }

    function moveItemBefore($id, $pid) {
        /* Place item after $pid in sequence */
        $item = $this->getByID($pid);

        $query = "UPDATE sideMenu SET sequence = (sequence + 1) WHERE parent = {$item['parent']} AND sequence > ({$item['sequence']} - 1 )";
        $this->db->query($query);

        parent::update(array('id'=>$id, 'sequence'=>$item['sequence'], 'parent'=>$item['parent']));
    }

    function moveItemLast($id, $pid) {
        /* Place item after $pid in sequence */
        $sequence = $this->getLastItemSequence($pid);

        parent::update(array('id'=>$id, 'sequence'=>$sequence, 'parent'=>$pid));
    }

    //This function resequences a portion of the menu to ensure that it stays in a sequential order
    //This function is called on updates (unecessary but will help occasionally) and deletes
    function resequenceMenu($parent) {
        $query = "SELECT id FROM sideMenu WHERE parent = $parent ORDER BY sequence, txt";
        $result = $this->db->query($query);

        for ($i = 0; $item = $this->db->getNextArray($result); $i++) {
            $seqquery = "UPDATE sideMenu SET sequence = $i WHERE id = {$item['id']}";
            $this->db->query($seqquery);
        }
    }

    function delete($id) {
        $item = $this->getByID($id);
        parent::delete($id);
        $this->deletePermissions($id);
        $this->resequenceMenu($item['parent']);
    }

    function getMenuItemCampusList($id) {
        $query = "SELECT campus FROM sideMenuCampus WHERE id = '$id'";
        $this->db->query($query);
        while($row = $this->db->getNextArray()) {
                $result[] = $row['campus'];
        }
        return $result;
    }

    function getMenuItemGroupList($id) {
        $query = "SELECT gid FROM sideMenuGroup WHERE id = '$id'";
       $this->db->query($query);
        while($row = $this->db->getNextArray()) {
                $result[] = $row['gid'];
        }
        return $result;
    }

    function addPermissions($id, $campus, $group) {
        if (is_array($campus)) {
            foreach ($campus as $campusID) {
                $this->db->insert('sideMenuCampus', array('id' => $id, 'campus' => $campusID));
            }
        }

        if (is_array($group)) {
            foreach ($group as $groupID) {
                $this->db->insert('sideMenuGroup', array('id' => $id, 'gid' => $groupID));
            }
        }
    }

    function deletePermissions($id) {
        $this->db->delete('sideMenuCampus', 'id', $id);
        $this->db->delete('sideMenuGroup', 'id', $id);
    }

    function outputChildren($id) {

    }
}
?>
