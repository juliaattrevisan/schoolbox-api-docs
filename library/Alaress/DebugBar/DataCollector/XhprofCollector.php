<?php
/**
 * Collector for XHProf runs - outputs into an iframe
 *
 * @todo make this configurable instead of hardcoding URL and namespace
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\DebugBar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use XHProfRuns_Default;

class XhprofCollector extends DataCollector implements Renderable {

    /**
     * Constructor: enables XHProf profiling
     */
    public function __construct() {
        if (extension_loaded('xhprof')) {
            xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function collect() {
        if (extension_loaded('xhprof')) {
            $profiler_namespace = 'schoolbox';
            $xhprof_data = xhprof_disable();

            $xhprof_runs = new XHProfRuns_Default();
            $run_id = $xhprof_runs->save_run($xhprof_data, $profiler_namespace);

            return array(
                'profile_url' => sprintf('/xhprof/index.php?run=%s&source=%s', $run_id, $profiler_namespace)
            );
        }
        return array();
    }

    /**
     * {@inheritDoc}
     */
    public function getName() {
        return 'xhprof';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets() {
        return array(
            "xhprof" => array(
                "widget" => "PhpDebugBar.Widgets.IFrameWidget",
                "map" => "xhprof.profile_url",
                "default" => ""
            )
        );
    }
}
