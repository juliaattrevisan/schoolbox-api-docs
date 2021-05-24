<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Terminology;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\L10n\Mapper as L10nMapper;

class Controller extends Base
{

    /**
     * @var L10nMapper
     */
    protected $l10nMapper;

    /**
     * Lets build this thing!
     */
    public function __construct()
    {
        $this->l10nMapper = L10nMapper::getInstance();
    }

    /**
     * Display the funky list editor thing
     */
    public function getIndex()
    {
        $this->getResponse()->content->set([
            'template' => '@adminTerminology/index.twig',
            'data'     => [
                'pageCurrent' => $this->router->generate('adminTerminology.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminTerminology.getIndex')),
                'terms' => $this->l10nMapper->findAll()
            ],
            'title' => 'Admin - Terminology'
        ]);
    }


    /**
     * Update the term
     */
    public function postUpdateTerms(): void
    {
        $post = $this->getRequest()->post;

        try {
            foreach ($post['singularForm'] as $term => $singularForm) {
                $localisation = $this->l10nMapper->findById($term)
                    ->setSingularForm(trim($singularForm))
                    ->setPluralForm(trim($post['pluralForm'][$term]))
                    ->setCapitaliseFirst(!empty($post['capitaliseFirst'][$term]))
                    ->setIndefiniteArticle(trim($post['indefiniteArticle'][$term]));

                $this->l10nMapper->save($localisation);
            }
        } catch (MapperException $e) {
            // noop, I guess? skip unknown terminologies
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminTerminology.getIndex'));
    }
}
