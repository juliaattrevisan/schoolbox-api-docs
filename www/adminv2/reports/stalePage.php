<?php


$this->setFile(TEMPLATE_content, 'admin/reports/stalePage.html');
$this->setBlock(TEMPLATE_content, 'stale', 'stale_block');

if(is_numeric($_POST['age']))
    $month = $_POST['age'];
else
    $month = 6;

$this->setVar($month.'_SELECTED', 'selected="selected"');

$query = "SELECT c.id, c.name, c.parent FROM category c
LEFT JOIN forumThread t ON t.categoryID = c.id AND last_post_date > DATE_SUB(NOW(), INTERVAL $month MONTH)
LEFT JOIN `file` f ON f.category = c.id AND f.modified > DATE_SUB(NOW(), INTERVAL $month MONTH)
LEFT JOIN link l ON l.category = c.id AND l.submitted > DATE_SUB(NOW(), INTERVAL $month MONTH)
LEFT JOIN subject_news sn ON sn.code = c.id AND sn.datetime_posted > DATE_SUB(NOW(), INTERVAL $month MONTH)
LEFT JOIN wiki w ON w.categoryID = c.id AND w.created > DATE_SUB(NOW(), INTERVAL $month MONTH)
LEFT JOIN poll p ON p.categoryID = c.id AND p.created > DATE_SUB(NOW(), INTERVAL $month MONTH)
LEFT JOIN homepage_config hc ON c.id = hc.cid
WHERE template > 0 AND  t.threadID IS NULL AND f.id IS NULL AND l.id IS NULL AND sn.id IS NULL AND w.wikiID IS NULL
AND p.pollID IS NULL";

$result = $this->db->query($query);

$folderMapper = new \Schoolbox\Resource\CategoryMapper();

while($row = $this->db->getNextArray($result)) {
    $loaf = array();

    $thisFolder = $folderMapper->findCheaplyById($row['id']);

    $breadcrumbs = $folderMapper->breadcrumb($thisFolder);

    foreach($breadcrumbs as $breadcrumb) {
        $loaf[] = "<a href='/homepage/{$breadcrumb->getId()}'>{$breadcrumb->getName()}</a>";
    }

    $loaf[] = "<a href='/homepage/{$thisFolder->getId()}'>{$thisFolder->getName()}</a>";

    $breadcrumbHTML = implode(' > ', $loaf);
            
    $this->setVar(array(
        'FOLDER_ID' => $row['id'],
        'FOLDER_NAME' => $row['name'],
        'BREADCRUMB' => $breadcrumbHTML
    ));

    $this->parse('stale_block','stale',true);
}