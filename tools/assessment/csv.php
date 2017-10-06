<?php
/**
 * Export assessment data for Accellerus import
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di;
use Schoolbox\Learning\Assessment\Exporter\Exporter;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\MapperFactory;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Term;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Assessment CSV');

$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {
    /**
     * @var Assessment\MapperProxy $assessmentDataSource
     * @var MapperFactory          $mapperFactory
     * @var Filter\Mapper          $filterMapper
     * @var Term\Mapper            $termMapper
     */
    $mapperFactory        = $di->get('mapperFactory');
    $filterMapper         = $mapperFactory->get(Filter\Mapper::class);
    $assessmentMapper     = $mapperFactory->get(Assessment\Mapper::class);
    $idFinder             = Filter\Filters\Assessment\IdFinder::getInstance($filterMapper, $assessmentMapper);
    $assessmentDataSource = $di->get('assessmentDataSource');
    $termMapper           = $mapperFactory->get(Term\Mapper::class);

    $exporter = new Exporter($idFinder, $assessmentDataSource, $termMapper);
    $exporter->generate(STDOUT);
});
