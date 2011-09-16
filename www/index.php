<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

/* XHProf libs */
/*if (extension_loaded('xhprof')) {
    include_once '/home/ryan/xhprof-0.9.2/xhprof_lib/utils/xhprof_lib.php';
    include_once '/home/ryan/xhprof-0.9.2/xhprof_lib/utils/xhprof_runs.php';

    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
}*/

if ( isset($_GET['page']) ) {
    $pageName = $_GET['page'];
} else {
    $pageName = 'dashboard';
}

$page = new SitePage();

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