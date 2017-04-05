<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Terminology;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\L10n\Mapper as L10nMapper;

class Controller extends Base
{
    const PAGE_CURRENT = 'terminology';

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
                'pageCurrent' => self::PAGE_CURRENT,
                'terms' => $this->l10nMapper->findAll()
            ],
            'title' => 'Admin - Terminology'
        ]);
    }

    /**
     * Update the term
     */
    public function postUpdateTerms()
    {
        $post = $this->getRequest()->post;

        try {

            foreach ($post['singularForm'] as $term => $singularForm) {
                $localisation = $this->l10nMapper->findById($term);
                $localisation->setSingularForm($singularForm);
                $localisation->setPluralForm($post['pluralForm'][$term]);
                $localisation->setCapitaliseFirst(!empty($post['capitaliseFirst'][$term]));
                $this->l10nMapper->save($localisation);
            }

        } catch (MapperException $e) {
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminTerminology.getIndex'));
    }
}
