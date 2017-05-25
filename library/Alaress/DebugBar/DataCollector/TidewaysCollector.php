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

class TidewaysCollector extends DataCollector implements Renderable {

    /**
     * Constructor: enables XHProf profiling
     */
    public function __construct() {
        if (extension_loaded('tideways')) {
            tideways_enable(TIDEWAYS_FLAGS_CPU + TIDEWAYS_FLAGS_MEMORY);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function collect() {
        if (extension_loaded('tideways')) {
            $profiler_namespace = 'schoolbox';
            $xhprof_data = tideways_disable();

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
        return 'tideways';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets() {
        return array(
            "Tideways XHprof" => array(
                "widget" => "PhpDebugBar.Widgets.IFrameWidget",
                "map" => "tideways.profile_url",
                "default" => ""
            )
        );
    }
}
