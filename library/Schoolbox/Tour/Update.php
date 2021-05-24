<?php

namespace Schoolbox\Tour;

use Schoolbox\Mapper\Exception;
use Symfony\Component\Yaml\Yaml;
use Schoolbox\Application\CliTool;

/**
 * Created for updating existing tour from a yaml file.
 * TODO: THIS IS NOT COMPLETED
 * This is created to fix a typo in the 20.2 tour.
 * At the moment, this works only for contents of a tour step modal.
 *
 * @package Schoolbox\Tour
 */
class Update extends CliTool\App
{
    public function run(): void
    {
        global $argv;

        if (!isset($argv[1])) {
            $filename = basename(__FILE__);
            echo
            <<<HELP
Attempt to update a tour written in YAML format

Usage: $filename [--perform] [FILE]

    --perform: use this flag to ensure changes are persisted, otherwise just shows what would happen

HELP
            ;
            exit;
        }

        $file = array_pop($argv);
        if (!(is_file($file) && is_readable($file))) {
            $this->getLogger()->error("File \"$file\" does not exist, or is not readable.");
            exit(1);
        }

        $this->getLogger()->warning('Currently, this script supports contents of a tour step modal only.');

        $tourSource = Yaml::parseFile($file);
        $tourSource = $tourSource['tour'];
        $title      = $tourSource['title'];

        // select uuid from tour where title = $title
        $tourMapper = Mapper::getInstance();
        try {
            /** @var Model $tour */
            $tour = $tourMapper->findOneByColumn('title', $title);
        } catch (Exception $e) {
            $this->getLogger()->error('Unable to find the tour: ' . $e->getMessage());
            exit();
        }
        $tourStepMapper = \Schoolbox\Tour\Step\Mapper::getInstance();
        $steps          = array_reverse($tourSource['steps']);
        $stepID         = null;

        $tourStep = null;
        foreach ($steps as $step) {
            $step = $step['step'];
            /** @var \Schoolbox\Tour\Step\Model $tourStep */

            try {
                $tourStep = $tourStepMapper->findOneByColumnList(
                    [
                        'tour_uuid' => $tour->getUuid(),
                        'type'      => $tourStepMapper::TYPE_MODAL,
                    ]
                );
            } catch (Exception\NotFound $e) {
                $this->getLogger()->info('No tour step to update');
                exit();
            }

            $tourModalMapper = \Schoolbox\Tour\Step\Type\Modal\Mapper::getInstance();
            /** @var \Schoolbox\Tour\Step\Type\Modal\Model $tourModal */
            $tourModal = $tourModalMapper->findOneByColumn('tour_step_uuid', $tourStep->getUuid());
            $tourModal->setBody($step['content']);

            if ($this->perform()) {
                $tourModalMapper->save($tourModal);
            }

            // TODO: support more than one step
            break;
        }
    }
}
