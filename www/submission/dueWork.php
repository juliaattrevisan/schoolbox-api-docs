<?php
/*
 *
 * redirect to Redirection to 16.5 url
 *
 */

$url = '/learning/due/';
if (isset($this->arg[1]) && is_numeric($this->arg[1])) {
    $this->localRedir($url . $this->arg[1], null, 301);
}
elseif (isset($this->arg[0]) && is_numeric($this->arg[0])) {
    $this->localRedir($url.$this->arg[0], null, 301);
    }
else {
    $this->pageNotFound();
}
