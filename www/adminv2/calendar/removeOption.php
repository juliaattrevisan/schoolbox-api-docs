<?php

/** @var SitePage $this */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403);
}

/* Remove record if it existed
 */
if (is_numeric($_POST['id'])) {
    $configRecord = Calendar_Api_Config_Mapper::getInstance()->findById(Scrub::text($_POST['id']));

    Calendar_Api_Config_Mapper::getInstance()->delete(array($configRecord));
}

exit();
