<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

/* XHProf libs */
/*if (extension_loaded('xhprof')) {
    include_once '/home/ryan/xhprof-0.9.2/xhprof_lib/utils/xhprof_lib.php';
    include_once '/home/ryan/xhprof-0.9.2/xhprof_lib/utils/xhprof_runs.php';

    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
}*/


// we shall assume that if a specific page is requested, the user just be sent to that page regardless of device
if((isset($_GET['page']) && $_GET['page']) && $_GET['page'] != '/') {
    $pageName = $_GET['page'];
}
else {
    // hey, are you a mobile? You should go check out the mobile site. As in, now. Scram.
    if(Cfg::get('mobile_enabled')) {
        $agent = new HttpUserAgent();
        if($agent->isMobile()) {
            SitePage::localRedir('/m/');
        }
    }
    $pageName = 'dashboard';
}

$page = new SitePage();

$mapper = new \Schoolbox\Assessment\Component\OnlineAssessmentMapper();
$models = $mapper->findAll();
$mapper->delete($models);

$page->loadContent($pageName);

$page->outputPage();

/*if (extension_loaded('xhprof')) {
    $profiler_namespace = 'schoolbox';
    $xhprof_data = xhprof_disable();

    $xhprof_runs = new XHProfRuns_Default();
    $run_id = $xhprof_runs->save_run($xhprof_data, $profiler_namespace);

    $profiler_url = sprintf('/xhprof/index.php?run=%s&source=%s', $run_id, $profiler_namespace);

    echo '<a href="'. $profiler_url .'" target="_blank">Profiler output</a>';
}*/
