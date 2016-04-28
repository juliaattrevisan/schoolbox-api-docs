<?php
/**
 * BlogMigrator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoobox\Blog\EportfolioBlog;

use Component\Eportfolio\MigratorAbstract;
use Component_Instance as ComponentInstance;
use Component_InstanceMapper as ComponentInstanceMapper;
use Schoolbox\Blog\EportfolioBlog\Model as EportfolioBlogModel;
use Schoolbox\Blog\EportfolioBlog\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\EportfolioComment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\EportfolioComment\Mapper as EportfolioBlogCommentMapper;
use Schoolbox\Collection;
use Schoolbox\UserMapper;
use Schoolbox\User;

class Migrator extends MigratorAbstract {

	/**
	 * Controller class for this component
	 *
	 * @var string
	 */
	protected $controllerClass = 'Schoolbox\\Blog\\EportfolioBlog\\Controller';

	/**
	 * Default name for this component
	 *
	 * @var string
	 */
	protected $componentDefaultName = 'My Blog';

	/**
	 * Setup
	 */
	protected function setup() 
	{
		// Grab instances of all the mappers

		$this->setUserMapper(UserMapper::getInstance());
		$this->setEportfolioBlogMapper(EportfolioBlogMapper::getInstance());
		$this->setEportfolioBlogCommentMapper(EportfolioBlogCommentMapper::getInstance());
		$this->setComponentInstanceMapper(ComponentInstanceMapper::getInstance());
	}

	/**
	 * Migrate this component
	 */
	public function migrate() 
	{
		/** @var Collection|EportfolioBlogModel $blogs */
		$blogs = $this->getEportfolioBlogMapper()->findByColumn("owner_id", $this->getUserId());		
		
		if ($blogs->count() > 0)  
		{
			$owner = $this->getUserMapper()->findById($this->getUserId());

			$component = new ComponentInstance();
			$component
				->setDefinitionId($this->getDefinitionId())
				->setIdentifier($this->getPageId())
				->setModule('eportfolio')
				->setSequence(0)
				->setPosition('left');

			$this->getComponentInstanceMapper()->save($component);
			
			/** @var EportfolioBlogModel $currentBlog */
			foreach ($blogs as $currentBlog) 
			{
				$newBlog = new EportfolioBlogModel();
				$newBlog->setPageId($this->getPageId());
				$newBlog->setOwner($owner);
				$newBlog->setHeading($currentBlog['heading']);
				$newBlog->setBody($currentBlog['body']);
				$newBlog->setCreatedAt($currentBlog['created_at']);

				$id = $this->getEportfolioBlogMapper()->save($newBlog);

				if (is_array($id) && count($id)) 
				{
					$comments = $this->getEportfolioBlogCommentMapper()->findByColumn("blog_id", $currentBlog['id']);					

					foreach ($comments as $currentComment) 
					{
						/** @var EportfolioBlogModel $blog */
						$blog = $this->getEportfolioBlogMapper()->findById(array_pop($id));

						$newComment = new EportfolioBlogCommentModel();
						$newComment->setBlog($blog);
						$newComment->setOwner($currentComment['owner']);
						$newComment->setBody($currentComment['body']);
						$newComment->setCreatedAt($currentComment['created']);

						$this->getEportfolioBlogCommentMapper()->save($newComment);
					}
				}
			}
		}
	}

	/**
	 * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
	 */

	/**
	 * @var UserMapper
	 */
	protected $userMapper;

	/**
	 * @var EportfolioBlogMapper;
	 */
	protected $blogMapper;

	/**
	 * @var EportfolioBlogCommentMapper;
	 */
	protected $blogCommentMapper;

	/**
	 * @var ComponentInstanceMapper;
	 */
	protected $componentInstanceMapper;


	/**
	 * @param $userMapper
	 */
	public function setUserMapper($userMapper) {
		$this->userMapper = $userMapper;
	}

	/**
	 * @return UserMapper
	 */
	public function getUserMapper() {
		return $this->userMapper;
	}

	/**
	 * @param $blogMapper
	 */
	public function setEportfolioBlogMapper($blogMapper) {
		$this->blogMapper = $blogMapper;
	}

	/**
	 * @return EportfolioBlogMapper
	 */
	public function getEportfolioBlogMapper() {
		return $this->blogMapper;
	}

	/**
	 * @param $componentInstanceMapper
	 */
	public function setComponentInstanceMapper($componentInstanceMapper) {
		$this->componentInstanceMapper = $componentInstanceMapper;
	}

	/**
	 * @return ComponentInstanceMapper
	 */
	public function getComponentInstanceMapper() {
		return $this->componentInstanceMapper;
	}

	/**
	 * @param $blogCommentMapper
	 */
	public function setEportfolioBlogCommentMapper($blogCommentMapper) {
		$this->blogCommentMapper = $blogCommentMapper;
	}

	/**
	 * @return EportfolioBlogCommentMapper
	 */
	public function getEportfolioBlogCommentMapper() {
		return $this->blogCommentMapper;
	}
}