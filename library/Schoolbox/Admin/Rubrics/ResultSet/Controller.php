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
    private const MAX_ROWS_PER_RESULT_SET = 11;

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
    public function getRubricResultsForCreate(): void
    {
        $this->getResponse()->content->set(
            [
                // Same template and data schema as for getRubricResults().
                'template' => '@adminRubric/index.twig',
                'data'     => [
                    'pageCurrent'      => $this->router->generate('adminRubric.getRubricResultsForCreate'),
                    'resultSetId'      => null,
                    'rubricResultRows' => [],
                    'allBreadcrumbs'   => (
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubric.getRubricResultsForCreate'))
                    )
                ]
            ]
        );
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
                    'MAX_ROWS_PER_RESULT_SET' => self::MAX_ROWS_PER_RESULT_SET,
                    'pageCurrent'             => $this->router->generate('adminRubric.getRubricResults'),
                    'resultSetId'             => $resultSetId,
                    'rubricResultRows'        => $this->mapper->findRubricResultRows((int)$resultSetId),
                    'allBreadcrumbs'          => (
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
    public function postRubricResults(?int $resultSetIdOrNull): void
    {
        $postedData    = $this->getRequest()->post->getArrayCopy();
        $indexedLabels = array();
        $labelsCount   = 0;

        foreach ($postedData['rubricResultLabels'] as $value => $label) {
            $indexedLabels[$value] = $label;

            if (++$labelsCount > self::MAX_ROWS_PER_RESULT_SET) {
                // The client should not send more than self::MAX_ROWS_PER_RESULT_SET to be saved.
                // In any case, we apply this limit at the server, and if it is surpassed we just ignore the excess rows.
                break;
            }
        }

        $resultSetId = $this->mapper->createOrOverwriteRubricResultSet($resultSetIdOrNull, $indexedLabels);

        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubric.getRubricResults', ['resultSetId' => $resultSetId]));
    }
}
