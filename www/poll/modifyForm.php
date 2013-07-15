<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

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
} else if ($_POST['title'] && is_numeric($_POST['categoryID'])) {

    /* Check permission
     */
    if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
        error(19);
    }

    $date = new Alaress_Date_Format($_POST['close'], 'd/m/y');

    $poll = array(
        'categoryID' => $_POST['categoryID'],
        'title' => $_POST['title'],
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
        $poll['pollID'] = $pollHandler->create($poll);
    }

    /* Select this poll in the component
     */
    $cdm = Component_ConfigDefinitionMapper::getInstance()->findByColumn('key', 'poll')->first();

    $confI = new Component_Config();
    $confI->configDefinitionId = $cdm->id;
    $confI->instanceId = $_POST['homepageComponentID'];
    $confI->value = $poll['pollID'];

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

    /* select next textBox
     */
    $current = $pollHandler->getCurrentPoll($poll['categoryID']);

    if ($current['pollID']) {
        $homepageHandler = new Homepage($current['categoryID']);

        $config = serialize(
            array(
                'pollID' => $current['pollID']
            )
        );

        $homepageHandler->updateComponentConfig(33, $config);
    }

    SitePage::localRedir("/homepage/" . $poll['categoryID']);
}
