<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Mapper\Exception;

class Controller extends ConfigController
{
    /**
     * Basically the same appearance as the usual system settings pages, but with special behaviours
     * because microsoft azure does its own thing.
     */
    public function getIndex(): void
    {
        $features = new Config()->fetchFeatures($this->getConfigMapper());

        $this->response = $this->responseFromTemplate('@adminOpenAi/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminOpenAi.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminOpenAi.getIndex')),
            'features' => $features,
            'fields' => Config::FIELDS,
            'providers' => Config::PROVIDERS,
            'models' => Config::AI_MODELS,
            'reasoningEffortModelOptions' => Config::REASONING_EFFORT_MODEL_OPTIONS,
        ], 'AI Services');
    }


    /**
     * Save the configuration for AI Services
     *
     * @return void
     * @throws RouteNotFound
     * @throws Exception|\JsonException
     */
    public function postOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        $aiConf = new Config();
        foreach (Config::ALL_MODULES as $feature => $configName) {
            // only update config if there is data in the post for that module
            if (!isset($postData[$feature])) {
                continue;
            }
            // get the config item
            $conf = $this->getConfigMapper()->findOneByColumn('name', $configName);
            // save the new config into that item
            try {
                $conf->setValue($aiConf->encode($postData[$feature], $feature));
            } catch (Exception $e) {
                throw new Exception("Failed to encode config for feature '$feature': " . $e->getMessage(), 0, $e);
            }
            $this->getConfigMapper()->save($conf);
        }

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminOpenAi.getIndex'));
    }
}
