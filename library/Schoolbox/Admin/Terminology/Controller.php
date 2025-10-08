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
     * Lets build this thing!
     */
    public function __construct(
        protected \Schoolbox\Config\ConfigMapper $configMapper,
        protected L10nMapper $l10nMapper
    ) {
    }

    /**
     * Display the funky list editor thing
     */
    public function getIndex()
    {
        $this->response = $this->responseFromTemplate(
            '@adminTerminology/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminTerminology.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminTerminology.getIndex')),
                'terms' => $this->l10nMapper->findAll()->sortNaturally('title'),
            ],
            'Admin - Terminology'
        );
    }


    /**
     * Update the term
     */
    public function postUpdateTerms(): void
    {
        $post = $this->requestPost;

        try {
            // Find all the terms that the user is trying to update
            $terms = array_keys($post['singularForm']);
            $localisations = $this->l10nMapper->findByColumn('term', $terms);

            foreach ($localisations as $localisation) {
                $term = $localisation->getTerm();

                $localisation
                    ->setSingularForm(trim($post['singularForm'][$term]))
                    ->setPluralForm(trim($post['pluralForm'][$term]))
                    ->setCapitaliseFirst(!empty($post['capitaliseFirst'][$term]))
                    ->setIndefiniteArticle(trim($post['indefiniteArticle'][$term]));

                $this->l10nMapper->save($localisation);
            }

            // Fetching the model so it can be updated since iConfigLoader is read-only
            // Update the last modified time so the cache can be invalidated
            $lastModified = $this->configMapper->findOneByColumn("name", "terminology_last_modified");
            $lastModified->setValue(time());
            $this->configMapper->save($lastModified);
        } catch (MapperException $e) {
            // noop, I guess? skip unknown terminologies
        }

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminTerminology.getIndex'));
    }
}
