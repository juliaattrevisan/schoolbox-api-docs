<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;

class Controller extends ConfigController
{
    /**
     * Controller constructor.
     */
    public function __construct() {
    }


    /**
     * Basically the same appearance as the usual system settings pages, but with special behaviours
     * because microsoft azure does its own thing.
     */
    public function getIndex(): void
    {
        $this->response = $this->responseFromTemplate('@adminOpenAi/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminOpenAi.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex')),
        ], 'AI Services');
    }


    /**
     * Save the configuration for AI Services
     *
     * @return void
     * @throws RouteNotFound
     * @throws Exception
     */
    public function postOpenAIOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Save it
         */
        $this->updateConfig($postData, $this->buildConfigPredicate());

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'));
    }

    /**
     *
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_OPENAI);
    }
}
