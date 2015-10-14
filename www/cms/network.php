<?php

// The old webdav browser - redirect to the new one.
/** @var SitePage $this */
$this->localRedir('/drives/'.implode('/', $this->arg), false, 301);
