<?php
/**
 * SocialStream_Ajax
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

class Threads {

	/**
	 * A schoolbox collection of SocialStream_Post instances
	 *
	 * @var null
	 */
	private $allPosts = null;

	/**
	 * Resultant tree structure
	 *
	 * @var null
	 */
	private $tree = null;

	/**
	 * The homepage which you are currently on
	 * This is derived from the component instance's identifier value
	 *
	 * @var int
	 */
	private $homepageId = 0;

	/**
	 * Only fetch N posts newer than $dateThreshold
	 *
	 * @var int
	 */
	private $start = 0;

	/**
	 * Only fetch posts newer than this date
	 *
	 * @var string
	 */
	private $offset = 0;

	/**
	 * The component instance
	 *
	 * @var null
	 */
	private $instance = null;

	/**
	 * The output html structure
	 *
	 * @var string
	 */
	private $ajaxHtml = '';

	/**
	 * Constructor
	 *
	 * @param $instance
	 * @param $limit
	 * @param $dateThreshold
	 */
	/**
	 * Constructor
	 *
	 * @param $instance
	 * @param $start
	 * @param $offset
	 */
	public function __construct($instance, $start, $offset) {
		$this->setInstance($instance);
		$this->setHomepageId($instance->getIdentifier());
		$this->setStart($start);
		$this->setOffset($offset);

		$this->init();
		$this->growTree();
	}

	/**
	 * Initialise data set before growing tree
	 */
	private function init() {

		/* Get all the posts that should appear
		 */
		$allPosts = \SocialStream\PostMapper::getInstance()->findLatestForHomepage($this->getHomepageId(), $this->getStart(), $this->getOffset());

		$ids = array();

		foreach($allPosts as $currentPost) {
			$ids[] = $currentPost->getPostId();
		}

		if (count($ids)) {
			/* Get each posts' hierarchy
			 */
			$tree = \SocialStream\PostMapper::getInstance()->findHierarchy($ids);

			/* And merge it all together
			 */
			$tree->merge($allPosts);
		}
		else {
			$tree = new \Schoolbox\Collection();
		}

		/* Save it for later!
		 */
		$this->setAllPosts($tree);
	}

	/**
	 * Grow a tree
	 *
	 * @return array
	 */
	private function growTree() {
		$structure = array();
		$remove = array();

		/* Move everything into an array to start with
		 */
		foreach($this->getAllPosts() as $currentPost) {
			$structure[] = $currentPost;
		}

		/* Push posts into their respective parents
		 */
		foreach($structure as $currentPost) {

			/* Only worry about moving deeper posts
			 */
			if ($currentPost->getParentId()) {
				$destination = $this->findNode($structure, $currentPost->getParentId());

				/* Only try to add it if a destination is found
				 * (This should get around orphaned posts)
				 */
				if ($destination) {
					$destination->getChildren()->add($currentPost);
				}

				/* Remember which ones we can remove from the root level
				 */
				$remove[] = $currentPost->getPostId();
			}
		}

		/* Nullify posts which have been moved elsewhere
		 */
		for ($i = 0; $i < count($structure); $i++) {
			if (in_array($structure[$i]->getPostId(), $remove)) {
				$structure[$i] = null;
			}
		}

		/* Remove posts which were nullified
		 */
		$tree = array_values(array_filter($structure));

		/* Transform it back into a collection, because we normally don't
		 * remove elements from iterator classes
		 */
		$collection = new \Schoolbox\Collection();

		foreach($tree as $node) {
			$collection->add($node);
		}

		/* Save that one for later!
	 	 */
		$this->setTree($collection);

		return $this;
	}

	/**
	 * Find a node in a tree
	 *
	 * @param $tree
	 * @param $id
	 * @return mixed
	 */
	private function findNode($tree, $id) {
		foreach($tree as $leaf) {
			if ($leaf->getPostId() == $id) {
				return $leaf;
			}
		}
	}

	/**
	 * Generate ajax html
	 */
	public function generate() {
		$page = new \SitePage();

		/* Determine whether you have access to the input form
		 */
		$newPost = new \SocialStream_Post_String();
		$currentUser = \Schoolbox\UserMapper::getInstance()->findById($_SESSION['id']);
		$authenticator = new Authenticator($newPost, $this->getInstance(), $currentUser);

		if ($authenticator->canComment()) {
			$canComment = '';
		}
		else {
			$canComment = 'hide';
		}

		/* Set up blocks and stuff
		 */
		$page->setFile('socialstream_thread', '/component/homepage/socialstreamThread.html');
		$page->setBlock('socialstream_thread', 'socialstream_post', 'socialstream_post_block');
		$page->setBlock('socialstream_post', 'socialstream_comment', 'socialstream_comment_block');
		$page->setBlock('socialstream_comment', 'socialstream_reply', 'socialstream_reply_block');

		/* Thumbnail stuff
		 */
		$page->setBlock('socialstream_post', 'socialstream_post_has_thumbnail', 'socialstream_post_has_thumbnail_block');
		$page->setBlock('socialstream_post', 'socialstream_post_has_player', 'socialstream_post_has_player_block');
		$page->setBlock('socialstream_comment', 'socialstream_comment_has_thumbnail', 'socialstream_comment_has_thumbnail_block');
		$page->setBlock('socialstream_comment', 'socialstream_comment_has_player', 'socialstream_comment_has_player_block');
		$page->setBlock('socialstream_reply', 'socialstream_reply_has_thumbnail', 'socialstream_reply_has_thumbnail_block');
		$page->setBlock('socialstream_reply', 'socialstream_reply_has_player', 'socialstream_reply_has_player_block');

		$page->setVar(array(
			'socialstream_post_block' => '',
			'socialstream_comment_block' => '',
			'socialstream_reply_block' => '',
			'socialstream_reply_has_thumbnail_block' => '',
			'socialstream_reply_has_player_block' => '',
		));

		/* Traverse everything
		 */
		$i = 0;

		foreach($this->getTree() as $currentPost) {
			$page->setVar(array(
				'SOCIALSTREAM_POST_POST_ID' => \Scrub::text($currentPost->getPostId()),
				'SOCIALSTREAM_POST_BODY' => \Scrub::text($currentPost->getBody()),
				'SOCIALSTREAM_POST_CREATED_AT' => \Scrub::text($currentPost->getCreatedAt()->getShort()),
				'SOCIALSTREAM_POST_USER_EXTERNAL_ID' => \Scrub::text($currentPost->getOwnerId()->getExternalId()),
				'SOCIALSTREAM_POST_USER_FULLNAME' => \Scrub::text($currentPost->getOwnerId()->getFullname()),
				'SOCIALSTREAM_POST_TIMESTAMP' => \Scrub::text($currentPost->getCreatedAt()->getUnixTimestamp()),
				'ON_OFF' => $i%2 ? 'dark' : '',
				'COMMENT_CLASS' => $canComment,
			));

			/* Thumbnails
			 */
			$page->setVar('socialstream_post_has_thumbnail_block', '');
			$page->setVar('socialstream_post_has_player_block', '');
			$currentPost->renderThumbnail('post', $page);

			/* Descendents
			 */
			foreach($currentPost->getChildren() as $currentComment) {
				$page->setVar(array(
					'SOCIALSTREAM_COMMENT_POST_ID' => \Scrub::text($currentComment->getPostId()),
					'SOCIALSTREAM_COMMENT_BODY' => \Scrub::text($currentComment->getBody()),
					'SOCIALSTREAM_COMMENT_CREATED_AT' => \Scrub::text($currentComment->getCreatedAt()->getShort()),
					'SOCIALSTREAM_COMMENT_USER_EXTERNAL_ID' => \Scrub::text($currentComment->getOwnerId()->getExternalId()),
					'SOCIALSTREAM_COMMENT_USER_FULLNAME' => \Scrub::text($currentComment->getOwnerId()->getFullname()),
					'COMMENT_CLASS' => $canComment,
				));

				/* Thumbnails
				 */
				$page->setVar('socialstream_comment_has_thumbnail_block', '');
				$page->setVar('socialstream_comment_has_player_block', '');
				$currentComment->renderThumbnail('comment', $page);

				/* Descendents
				 */
				foreach($currentComment->getChildren() as $currentReply) {
					$page->setVar(array(
						'SOCIALSTREAM_REPLY_POST_ID' => \Scrub::text($currentReply->getPostId()),
						'SOCIALSTREAM_REPLY_BODY' => \Scrub::text($currentReply->getBody()),
						'SOCIALSTREAM_REPLY_CREATED_AT' => \Scrub::text($currentReply->getCreatedAt()->getShort()),
						'SOCIALSTREAM_REPLY_USER_EXTERNAL_ID' => \Scrub::text($currentReply->getOwnerId()->getExternalId()),
						'SOCIALSTREAM_REPLY_USER_FULLNAME' => \Scrub::text($currentReply->getOwnerId()->getFullname()),
					));

					/* Thumbnails
					 */
					$page->setVar('socialstream_reply_has_thumbnail_block', '');
					$page->setVar('socialstream_reply_has_player_block', '');
					$currentReply->renderThumbnail('reply', $page);

					/* Clear children after parsing out the parent
					 */
					$page->parse('socialstream_reply_block', 'socialstream_reply', true);
					$page->setVar('socialstream_reply_has_thumbnail_block', '');
					$page->setVar('socialstream_reply_has_player_block', '');
				}

				/* Clear children after parsing out the parent
				 */
				$page->parse('socialstream_comment_block', 'socialstream_comment', true);
				$page->setVar('socialstream_comment_has_thumbnail_block', '');
				$page->setVar('socialstream_comment_has_player_block', '');
				$page->setVar('socialstream_reply_block', '');
			}

			/* Clear children after parsing out the parent
			 */
			$page->parse('socialstream_post_block', 'socialstream_post', true);
			$page->setVar('socialstream_post_has_thumbnail_block', '');
			$page->setVar('socialstream_post_has_player_block', '');
			$page->setVar('socialstream_comment_block', '');
			$page->setVar('socialstream_reply_block', '');

			/* Alternating colours -_-
			 */
			$i++;
		}

		/* Save output for later!
		 */
		$this->setAjaxHtml($page->template->_varVals['socialstream_post_block']);

		/* Chain it
		 */
		return $this;
	}

	/**
	 * Render out a bunch of html containing each SocialStream Post
	 */
	public function render() {
		return $this->getAjaxHtml();
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $allPosts
	 */
	private function setAllPosts($allPosts) {
		$this->allPosts = $allPosts;
	}

	/**
	 * @return null
	 */
	private function getAllPosts() {
		return $this->allPosts;
	}

	/**
	 * Set tree
	 *
	 * @param null $tree
	 */
	private function setTree($tree) {
		$this->tree = $tree;
	}

	/**
	 * Get tree
	 *
	 * @return null
	 */
	private function getTree() {
		return $this->tree;
	}

	/**
	 * Set homepage id
	 *
	 * @param $homepageId
	 */
	private function setHomepageId($homepageId) {
		$this->homepageId = $homepageId;
	}

	/**
	 * Get homepage id
	 *
	 * @return int
	 */
	private function getHomepageId() {
		return $this->homepageId;
	}
	/**
	 * Set instance
	 *
	 * @param null $instance
	 */
	private function setInstance($instance) {
		$this->instance = $instance;
	}

	/**
	 * Get instance
	 *
	 * @return null
	 */
	private function getInstance() {
		return $this->instance;
	}

	/**
	 * Set ajax html
	 *
	 * @param string $ajaxHtml
	 */
	private function setAjaxHtml($ajaxHtml) {
		$this->ajaxHtml = $ajaxHtml;
	}

	/**
	 * Get ajax html
	 *
	 * @return string
	 */
	private function getAjaxHtml() {
		return $this->ajaxHtml;
	}

	/**
	 * Set start
	 *
	 * @param int $start
	 */
	public function setStart($start) {
		$this->start = $start;
	}

	/**
	 * Get start
	 *
	 * @return int
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * Set offset
	 *
	 * @param string $offset
	 */
	private function setOffset($offset) {
		$this->offset = $offset;
	}

	/**
	 * Get offset
	 *
	 * @return string
	 */
	private function getOffset() {
		return $this->offset;
	}
}