<?php
require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

$page = new SitePage();
$pollHandler = new Poll();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_POST['pollID']) && is_numeric($_POST['categoryID']) && isset($_POST['vote'])) {

    /* Check permission
     */
    if ($groupHandler->getPermission($_POST['categoryID']) < READ_ACCESS) {
        error(19);
    }

    $pollHandler->placeVote($_POST['pollID'], $_POST['vote']);

    SitePage::localRedir("/homepage/" . $_POST['categoryID']);
} else if ($_POST['question'] && is_numeric($_POST['categoryID'])) {

    /* Check permission
     */
    if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
        error(19);
    }

    $date = Alaress_Date_Format::newLocalShort($_POST['close']);

    $poll = array(
        'categoryID' => $_POST['categoryID'],
        'question' => $_POST['question'],
        'option_0' => $_POST['option_0'],
        'option_1' => $_POST['option_1'],
        'option_2' => $_POST['option_2'],
        'option_3' => $_POST['option_3'],
        'option_4' => $_POST['option_4'],
        'option_5' => $_POST['option_5'],
        'option_6' => $_POST['option_6'],
        'option_7' => $_POST['option_7'],
        'option_8' => $_POST['option_8'],
        'option_9' => $_POST['option_9'],
        'frequency' => $_POST['frequency'],

        'close' => $date->getDatabase(),
    );

    if (is_numeric($_POST['pollID'])) {
        $poll['pollID'] = Scrub::text($_POST['pollID']);

        $pollHandler->update($poll);
    } else {
        $poll['created_by'] =  $_SESSION['id'];
        $poll['created'] = Alaress_Date_Format::now()->getDatabase();
        $poll['pollID'] = $pollHandler->create($poll);
    }

    /* Select this poll in the component
     */
    try {
        $cdm = Component_ConfigDefinitionMapper::getInstance()->findOneByColumn('key', 'poll');
        $cim = Component_InstanceMapper::getInstance()->findOneByColumnList(
            array(
                'id'                      => $_POST['homepageComponentID'],
                'module'                  => 'homepage',
                'identifier'              => $_POST['categoryID'],
                'component_definition_id' => $cdm->getDefinitionId(),
            )
        );
    }
    catch (Schoolbox\Mapper\Exception $e) {
        SitePage::localRedir("/homepage/" . $poll['categoryID']);
    }

    $confI = new Component_Config();
    $confI->setConfigDefinitionId($cdm->getId());
    $confI->setInstanceId($cim->getId());
    $confI->setValue($poll['pollID']);

    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();
    $componentConfigInstanceMapper->save($confI);


    SitePage::localRedir('/homepage/' . Scrub::text($_POST['categoryID']));
} else if (is_numeric($_GET['delete'])) {
    $poll = $pollHandler->getByID($_GET['delete']);

    /* Check permission
     */
    if ($groupHandler->getPermission($poll['categoryID']) < WRITE_ACCESS) {
        error(19);
    }

    $pollHandler->delete($_GET['delete']);

    /* do not select next poll, that's just dumb
     * I deleted this poll, then I don't expect to be Hydra'd by another poll
     * spawning in its place
     */
    try {
        $cdm = Component_ConfigDefinitionMapper::getInstance()->findOneByColumn('key', 'poll');
        $cim = Component_InstanceMapper::getInstance()->findOneByColumnList(
            array(
                'id'                      => $_GET['homepageComponentID'],
                'module'                  => 'homepage',
                'identifier'              => $poll['categoryID'],
                'component_definition_id' => $cdm->getDefinitionId(),
            )
        );
    }
    catch (Schoolbox\Mapper\Exception $e) {
        error(19);
        exit;
    }

    $confI = new Component_Config();
    $confI->setConfigDefinitionId($cdm->getId());
    $confI->setInstanceId($cim->getId());
    $confI->setValue(null);

    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();
    $componentConfigInstanceMapper->save($confI);

    SitePage::localRedir("/homepage/" . $poll['categoryID']);
}
