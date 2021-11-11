<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$pollHandler = new Poll();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

if (is_numeric($_POST['id']) && is_numeric($_POST['folder_id']) && isset($_POST['vote'])) {
    /* Check permission
     */
    if ($groupHandler->getPermission($_POST['folder_id']) < UserAccess::ACCESS_READ) {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    $pollHandler->placeVote($_POST['id'], $_POST['vote']);

    SitePage::localRedir("/homepage/" . $_POST['folder_id']);
} else if ($_POST['question'] && is_numeric($_POST['folder_id'])) {
    /* Check permission
     */
    if ($groupHandler->getPermission($_POST['folder_id']) < UserAccess::ACCESS_WRITE) {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    $date = Alaress_Date_Format::newLocalShort($_POST['close']);

    $poll = array(
        'folder_id' => $_POST['folder_id'],
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

    if (is_numeric($_POST['id'])) {
        $poll['id'] = Scrub::text($_POST['id']);

        $pollHandler->update($poll);
    } else {
        $poll['created_by'] =  $_SESSION['id'];
        $poll['created'] = Alaress_Date_Format::now()->getDatabase();
        $poll['id'] = $pollHandler->create($poll);
    }

    /* Select this poll in the component
     */
    $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();
    $componentInstanceMapper = Component_InstanceMapper::getInstance();

    try {
        $cd = $componentConfigDefinitionMapper->findOneByColumn('key', 'poll');
        $ci = $componentInstanceMapper->findOneByColumnList([
            'id'                      => $_POST['homepageComponentID'],
            'module'                  => 'homepage',
            'identifier'              => $_POST['folder_id'],
            'component_definition_id' => $cd->getDefinitionId(),
        ]);
    } catch (Schoolbox\Mapper\Exception $e) {
        /* noop something went wrong; couldn't find config info
         * just goes back to homepage
         */
        SitePage::localRedir('/homepage/' . Scrub::text($_POST['folder_id']));
    }

    try {
        /* Look for an existing component configuration for this component
         * If one exists, just reuse its stuff
         */
        $componentConfigInstance = $componentConfigInstanceMapper->findOneByColumnList([
            'component_config_definition_id' => $cd->getId(),
            'component_instance_id' => (int)$_POST['homepageComponentID']
        ]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        /* Otherwise, create a new component configuration
         */
        $componentConfigInstance = $componentConfigInstanceMapper->createModel();
    }

    /* Then update its value to be the new poll id
     */
    $componentConfigInstance->setConfigDefinitionId($cd->getId())
                            ->setInstanceId($ci->getId())
                            ->setValue($poll['id']);

    $componentConfigInstanceMapper->save($componentConfigInstance);

    SitePage::localRedir('/homepage/' . Scrub::text($_POST['folder_id']));
} else if ($_POST['delete'] === 'true') {
    //get the id and other post objects
    $id = (int)($_POST['id'] ?? null);
    $componentId = (int)($_POST['componentId'] ?? null) ;

    //lets confirm there is actually an id and component id...
    if (!$id || !$componentId) {
        $page->outputJson(['message' => 'Poll id not provided'], false, 400);
        return;
    }

    //get the poll object if it exists? multiplayer schoolbox
    $poll = $pollHandler->getByID($id);
    if ($poll === false) {
        $page->outputJson(['message' => 'Poll not found, has it already been deleted?'], false, 400);
        return;
    }

    /* Check permission
     */
    if ($groupHandler->getPermission($poll['folder_id']) < UserAccess::ACCESS_WRITE) {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    $pollHandler->delete($id);

    /* do not select next poll, that's just dumb
     * I deleted this poll, then I don't expect to be Hydra'd by another poll
     * spawning in its place
     */
    try {
        $cdm = Component_ConfigDefinitionMapper::getInstance()->findOneByColumn('key', 'poll');
        $cim = Component_InstanceMapper::getInstance()->findOneByColumnList(
            [
                'id'                      => $componentId,
                'module'                  => 'homepage',
                'identifier'              => $poll['folder_id'],
                'component_definition_id' => $cdm->getDefinitionId(),
            ]
        );
    } catch (Schoolbox\Mapper\Exception $e) {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        exit;
    }

    //find component config object
    try {
        $confI = Component_ConfigMapper::getInstance()->findOneByColumnList(
            [
                'component_config_definition_id' => $cdm->getId(),
                'component_instance_id'          => $cim->getId()
            ]
        );
        $confI->setValue(null);
    } catch (Schoolbox\Mapper\Exception $e) {
        $page->outputJson(['message' => 'Unable to find component to update'], false, 400);
        return;
    }

    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();
    $componentConfigInstanceMapper->save($confI);

    $page->outputJson(
        [
            'message' => 'Poll deleted',
            'poll_id' => $poll['id'],
            'homepage_id' => $poll['folder_id'],
        ],
        false,
        200
    );
}
