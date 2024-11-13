<?php

/**
 * Controller for the base of the curriculum module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Curriculum\Node\Model;

class Controller extends Base
{
    /**
     * @var Node\Mapper
     */
    private $nodeMapper;

    /**
     * This is used when retrieving nodes from Curriculum UI, using api endpoint
     * The default values for API are defined in Curriculum/API/Controller
     */
    private const DEFAULT_UI_LIMIT = 1000;

    /**
     * Constructor
     *
     * @param Node\Mapper          $nodeMapper
     */
    public function __construct(
        Node\Mapper $nodeMapper
    ) {
        $this->nodeMapper      = $nodeMapper;
    }


    /**
     * Controller action for browsing the list of curricula.
     */
    public function browse()
    {
        // Fetch all the nodes at once, and parse into a jstree like status
        $tree = array();
        foreach ($this->nodeMapper->findAll() as $node) {
            /** @var Node\Model $node */
            $tree[] = $this->getAsJsTree($node);
        }

        // Load parent nodes and all of their subnodes, and dump them in a tree
        $this->response = $this->responseFromTemplate(
            '@curriculum/browse.twig',
            [
                'tree'   => $tree,
                // Readonly check - for now, check without any parameters
                'readonly' => !$this->getAuthorizer()->isPathAuthorizedForUser(
                    'curriculum.edit',
                    [],
                    $this->getUser()
                ),
                'uiLimit' => self::DEFAULT_UI_LIMIT,
            ],
            'Curriculum',
        );
    }

    /**
     * JSON search for curriculum nodes
     */
    public function search()
    {
        $search = trim($this->requestQuery['query']);
        $this->response = $this->responseAsJson(
            $this->nodeMapper->search(
                $this->getSession()->getAuthUser(),
                $search
            )
        );
    }

    /**
     * Fetch an entire tree of curriculum nodes.
     *
     * @api   GET /curriculum/tree
     * @since 22.1
     */
    public function getTree(): void
    {
        $tree = $this->nodeMapper->getCollectionAsTree(
            $this->nodeMapper->findAll(),
            'parent',
            'children',
        );
        $this->response = $this->responseAsJson($tree);
    }

    /**
     * Helper method for fetching a node as a JsTree entry.
     *
     * @param  Node\Model $node
     * @return array
     */
    protected function getAsJsTree(Node\Model $node)
    {
        $nodeId = (int)$node->getId();
        $name   = $node->getName();
        $isCode = \strlen($node->getCode()) > 0;
        if ($isCode) {
            $name = sprintf('[%s] %s', $node->getCode(), $name);
        }
        return array(
            'id'     => $nodeId,
            'parent' => $node->getParent() !== null ? (int)$node->getParent()->getId() : '#',
            'text'   => $name,
            'a_attr' => array(
                'href' => $this->routeGenerator->generate(
                    'curriculum.read',
                    array('id' => $nodeId, 'format' => '')
                ),
                'data-saveuri' => $this->routeGenerator->generate(
                    'curriculum.update:post',
                    array('id' => $nodeId)
                ),
                'data-deleteuri' => $this->routeGenerator->generate(
                    'curriculum.delete:post',
                    array('id' => $nodeId)
                ),
            ),
            'li_attr' => array(
                'title' => $name,
            ),
            'type'    => ($isCode ? 'code' : 'folder'),
        );
    }

    /**
     * Reads a node and outputs it.
     *
     * @param Node\Model $item
     */
    public function read(Node\Model $item)
    {
        $this->response = $this->responseFromTemplate(
            '@curriculum/read.twig',
            ['item' => $item],
            'Curriculum'
        );
    }

    /**
     * Creates a new node
     */
    public function create()
    {
        $item = $this->nodeMapper->createModel();

        // Default learning area and level, or a preselected one?

        $post = $this->requestPost;
        $code = 200;

        // Perform necessary validations, if any.
        $item
            ->setName($post['name'])
            ->setCode(trim($post['code']))
            ->setReference($post['reference'])
            ->setDescription($post['description']);

        // set this node's area and level to its parents,
        // if it has parent.
        // (Do this in preSave?)
        $parent = null;
        if ($post['parent']) {
            $parent = $this->nodeMapper->findById($post['parent']);
        }
        $item
            ->setParent($parent);

        // Save the item
        if (!$this->nodeMapper->save($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson(
                [
                    'item' => $item,
                    'tree' => $this->getAsJsTree($item),
                ],
            )->withStatus($code);
        } else {
            // Redirect back to the the last page?
            // Or elsewhere, should validation have failed
            $this->response = $this->redirectAfterPost(
                $this->getSession()->getHistory()
            );
        }
    }

    /**
     * Saves a curriculum.
     *
     * @param Node\Model $item
     */
    public function update(Node\Model $item)
    {
        $post = $this->requestPost;
        $code = 200;

        // Perform necessary validations, if any.
        if (isset($post['name'])) {
            $item->setName($post['name']);
        }
        if (isset($post['code'])) {
            $item->setCode(trim($post['code']));
        }
        if (isset($post['reference'])) {
            $item->setReference($post['reference']);
        }
        if (isset($post['description'])) {
            $item->setDescription($post['description']);
        }

        // Parent: check this node isn't its own ancestor

        // Save the item
        if (!$this->nodeMapper->save($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson(
                [
                    'item' => $item,
                    'tree' => $this->getAsJsTree($item),
                ],
            )->withStatus($code);
        } else {
            // Redirect back to the the last page?
            // Or elsewhere, should validation have failed
            $this->response = $this->redirectAfterPost(
                $this->getSession()->getHistory()
            );
        }
    }

    /**
     * Delete action
     *
     * @param Node\Model $item
     */
    public function delete(Node\Model $item)
    {
        $code = 200;
        if (!$this->nodeMapper->delete($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson(
                [
                    'item' => $item,
                    'tree' => $this->getAsJsTree($item),
                ],
            )->withStatus($code);
        } else {
            // Redirect back to the the last page?
            // Or elsewhere, should validation have failed
            $this->response = $this->redirectAfterPost(
                $this->getSession()->getHistory()
            );
        }
    }

    /**
     * Export all the curriculum nodes, in a format suitable for import.
     * Must be Superuser or Curriculum Write+ users
     *
     * @api  GET /curriculum/export/{id}
     * @since 24.1
     */
    public function getExport(Node\Model $item): void
    {
        $node = $this->nodeMapper->loadDescendants($item, 'parent', 'children');

        $filename = sprintf("CurriculumExport-%s-%s.csv", ConfigMapper::NAME_SITE_SCHOOL_CODE, \Alaress_Date_Format::now()->toIso8601String());

        $fp = fopen('php://temp', 'w');

        $this->response = $this->responseFromStream(new \GuzzleHttp\Psr7\Stream($fp), $filename, 'text/csv; charset=utf-8');

        // output headers
        fputcsv($fp, ['id', 'curriculum_node_parent_id', 'name', 'code', 'order', 'description', 'uuid']);

        $this->csvChildren($fp, $node, true);
    }

    /**
     * @param $fp
     * @param Model $node
     * @param bool $isFirst
     * @return void
     */
    private function csvChildren($fp, Node\Model $node, bool $isFirst = false): void
    {
        $parent = $node->getParent() ? $node->getParent()->getId() : '';
        if ($isFirst) {
            $parent = '';
        }
        fputcsv($fp, [
            $node->getId(),
            $parent,
            $node->getName(),
            $node->getCode(),
            $node->getOrder(),
            $node->getDescription(),
            $node->getUuid(),
        ]);

        /**
         * for each of this node's children, csv output its children recursively
         */
        foreach ($node->getChildren() as $child) {
            $this->csvChildren($fp, $child);
        }
    }
}
