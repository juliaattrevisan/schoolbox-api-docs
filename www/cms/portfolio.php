<?php

// The old myfiles browser - redirect to the new one.
/** @var SitePage $this */
$this->localRedir('/my-files/'.implode('/', $this->arg), false, 301);
