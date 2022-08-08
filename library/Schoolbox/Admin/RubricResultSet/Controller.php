<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\RubricResultSet;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;

/**
 * RubricResultSet controller class.
 */
class Controller extends Base
{
    // The number of rows corresponds to the number of levels in a rubric grid.
    // Our ui limits it arbitrarily to 11, despite the schema design permitting you to have more than that.
    public const MAX_ROWS_PER_RESULT_SET = 11;

    // This limit has been arbitrarily chosen.  The database schema allows many more characters, but rubric result
    // labels should only be a few words, so a sensible limit is enforced at the client and server sides.
    public const MAX_CHARS_PER_LABEL = 64;

    /**
     * Controller constructor.
     * @param Mapper $mapper
     */
    public function __construct(private Mapper $mapper)
    {
    }

    /**
     * Show a list of rubric result sets.
     * @api GET /adminv2/rubric-result-set
     * @since 22.1
     * @throws RouteNotFound
     */
    public function getList(): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubricResultSet/index.twig',
                'data'     => [
                    'pageCurrent'          => $this->router->generate('adminRubricResultSet.getList'),
                    'rubricsByResultSetId' => $this->mapper->findSets(),
                    'allBreadcrumbs'       => (
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubricResultSet.getList'))
                    )
                ]
            ]
        );
    }

    /**
     * Delete the result set defined by $resultSetId.
     * @api DELETE /adminv2/rubric-result-set/{resultSetId}
     * @since 22.1
     * @param int|null $resultSetId
     */
    public function deleteResultSet(?int $resultSetId): void
    {
        $this->mapper->deleteSet((int)$resultSetId);
    }

    /**
     * @api GET /adminv2/rubric-result-set/create
     * @since 22.1
     * @throws RouteNotFound
     */
    public function getCreate(): void
    {
        // A getCreate request is equivalent to a getUpdate request with resultSetId set to null.
        $this->getUpdate(null);
    }

    /**
     * @api POST /adminv2/rubric-result-set/create
     * @since 22.1
     * @throws RouteNotFound
     */
    public function postCreate(): void
    {
        // A postCreate request is equivalent to a postUpdate request with resultSetId set to null.
        $this->postUpdate(null);
    }

    /**
     * Shows a list of labels belonging to a particular rubric result set.
     * @api GET /adminv2/rubric-result-set/{resultSetId}
     * @since 22.1
     * @param int|null $resultSetId
     * @throws RouteNotFound
     */
    public function getUpdate(?int $resultSetId): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubricResultSet/create.twig',
                'data'     => [
                    'maxCharsPerLabel'    => self::MAX_CHARS_PER_LABEL,
                    'maxRowsPerResultSet' => self::MAX_ROWS_PER_RESULT_SET,
                    'pageCurrent'         => $this->router->generate('adminRubricResultSet.getList'),
                    'resultSetId'         => $resultSetId,
                    'rubricResultRows'    => (($resultSetId === null) ? [] : $this->mapper->findRows($resultSetId)),
                    'allBreadcrumbs'      => (
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubricResultSet.getList'))
                    )
                ]
            ]
        );
    }

    /**
     * Save $_POSTed data to database, then redirect to getList.
     * @api POST /adminv2/rubric-result-set/{resultSetId}
     * @since 22.1
     * @param int|null $resultSetId
     * @throws RouteNotFound
     */
    public function postUpdate(?int $resultSetId): void
    {
        $postedData    = $this->request->post->getArrayCopy();
        $indexedLabels = [];

        foreach ($postedData['rubricResultLabels'] as $value => $label) {
            // The client should not send more than self::MAX_ROWS_PER_RESULT_SET to be saved.
            // In any case, we apply this limit at the server, and if it is surpassed we just ignore the excess rows.
            if (count($indexedLabels) < self::MAX_ROWS_PER_RESULT_SET) {
                $indexedLabels[$value] = $label;
            }
        }

        $this->mapper->saveSet($resultSetId, $indexedLabels); // If $resultSetId is null, a new set will be created.

        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubricResultSet.getList'));
    }
}
