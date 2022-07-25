<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Rubrics\ResultSet;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Exception;
use http\Exception\InvalidArgumentException;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * Controller constructor.
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Load templates/index.twig using data obtained in this function.
     * @throws RouteNotFound
     */
    public function getRubricResults($resultSetId): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubric/index.twig',
                'data'     => [
                    'pageCurrent'      => $this->router->generate('adminRubric.getRubricResults'),
                    'resultSetId'      => $resultSetId,
                    'rubricResultRows' => $this->mapper->findRubricResultRows((int)$resultSetId),
                    'allBreadcrumbs'   => (
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubric.getRubricResults'))
                    )
                ]
            ]
        );
    }

    /**
     * Save $_POSTed data to database, then redirect to getRubricResults().
     * @throws Exception
     */
    public function postRubricResults($resultSetId): void
    {
// TODO: Get entire $_POST array using $this->getRequest() somehow.
//        $postedData = $this->getRequest()->post;
        $postedData    = $_POST;
        $indexedLabels = array();

        foreach ($postedData as $key => $value) {
            // Expect $key to be in format "row-$n-label".  Where $n is rubric_result.value.
            $partialKey = str_replace('row-', '', $key);
            $n          = str_replace('-label', '', $partialKey);

            if (!ctype_digit($n)) {
                throw new \InvalidArgumentException("Could not parse integer \$n from 'row-\$n-value' string.  Found '$n' instead of integer.");
            }

            $indexedLabels[$n] = $value;
        }

        $this->mapper->createOrOverwriteRubricResultSet((int)$resultSetId, $indexedLabels);

        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubric.getRubricResults'));
    }
}
