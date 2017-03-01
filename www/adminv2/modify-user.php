<?php

/**
 * This is now redundant - redirect to new modify/insert page depending on if an ID was passed
 *
 *    |\      _,,,---,,_
 *   /,`.-'`'    -.  ;-;;,_
 *  |,4-  ) )-,_..;\ (  `'-'
 * '---''(_/--'  `-'\_)
 *
 */
.
if (is_numeric($this->arg[0])) {
    $url = $this->getDi()->get('router')->generate('adminUser.getModify', ['userId' => $this->arg[0]]);
} else {
    $url = $this->getDi()->get('router')->generate('adminUser.getInsert');
}

SitePage::localRedir($url);
