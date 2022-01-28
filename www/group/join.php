<?php

use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\UserAccessMapper;

/*
 * redirect to new groups page, this page is no longer used [SA-12873]
 */
header("location: {$this->routeLookup('groups.getNewGroups')}#groups-i-can-join", true);
exit;
