<?php
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

echo Cfg::get('xml_dir');

mkdir(Cfg::get('xml_dir') . '/../stream', 0777);
chmod(Cfg::get('xml_dir') . '/../stream', 0777);
