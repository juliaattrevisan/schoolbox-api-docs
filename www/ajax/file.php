<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$output = array();
$allowFilter = array('image', 'video');

if (is_numeric($_POST['categoryID'])) {
/* Output a list of nodes (which are comprised of both files and links)
     * in the second column of Old Resource Browser
     *
     * Root folder is zero and no links should be in there
     * Root folder is the default location to open upon first visit
     * and when a folder doesn't exist anymore
     */
    $category = new \Schoolbox\Resource\Category();
    $category->setId(0);

    if ($_POST['categoryId']) {
        try {
            $categoryMapper = new \Schoolbox\Resource\CategoryMapper();
            $category = $categoryMapper->findById($_POST['categoryID']);
        } catch (\Exception $e) {
            error(13);
        }
    }

    /* Also used in tinyMCE resource browser
     */
    $filterOptions = null;

    if (in_array($_POST['filter'], $allowFilter)) {
        $filterOptions = array(
            'filter' => $_POST['filter']
        );
    }

    /* The usual nodes
     */
    $contents = new \Schoolbox\Resource\Browser\Tree\Contents($category, $filterOptions);
    $allNodes = $contents->getAllNodes();

    /* Prepend the homepage node, a custom manufacture
     *
     * This is used in the tinyMCE resource browser popup, when selecting
     * links to a Homepage from within a folder
     */
    if (isset($_POST['homelink']) && is_numeric($category->getHomepage()->getTemplate()->getId())) {
        $icon = new \Schoolbox\Resource\Browser\Tree\Node\Icon();
        $icon->setCss('');
        $icon->setFile('vcard_fiquet.png');

        $node = new \Schoolbox\Resource\Browser\Tree\Node();
        $node->setNodeId($category->getId());
        $node->setUser(new \Schoolbox\User());
        $node->setType('file');
        $node->setIcon($icon);
        $node->setName('Homepage');
        $node->setCategory($category);
        $node->setCss(array());
        $node->setModifiedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

        $allNodes->prepend($node);
    }

    /* Mash it out
     */
    foreach ($allNodes as $currentNode) {
        $adr = new \Alaress_Date_Format($currentNode->getModifiedAt()->getUnixTimestamp(), 'U');
        $output[] = array(
            'id' => $currentNode->getNodeId(),
            'user' => $currentNode->getUser()->formatName(),
            'relative' => $adr->getRelativeTime(),
            'mimeClass' => $currentNode->getIcon()->getCss(),
            'iconFile' => $currentNode->getIcon()->getFile(),
            'description' => $currentNode->getName(),
        );
    }

    $_SESSION['resourcesID'] = $_POST['categoryID'];
}

header("Content-type: application/json");
print json_encode($output);
