<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FeedController extends Component_Homepage_ControllerAbstract {

	/** @var \Schoolbox\Collection|\Schoolbox\Feed\Item[] */
	protected $items;

	/**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
	public function execute() {

		$conf = \Schoolbox\Config\ConfigMapper::getInstance();
		$feed = new \Schoolbox\Feed\Feed($conf);

		$this->items = $feed->aggregate(
			array(
				new \Schoolbox\Assessment\Component\SubmissionBoxNotifier($conf),
				new \Schoolbox\Resource\Category\NewsNotifier($conf),
				new \Schoolbox\Blog\BlogNotifier($conf),
				new \Schoolbox\Blog\CommentNotifier($conf)
			),
			$this->getContext('id'), $this->getConfig('size')
		);
	}

	/**
	 * Renders the data into the SitePage. Presentation logic.
	 * @param String $templateHandle
	 * @return boolean
	 */
	public function renderTemplate($templateHandle) {
		if (!parent::loadTemplate($templateHandle, 'feed')) {
			return false;
		}
		$this->parseConfig();

		$this->setVar('USER_TOKEN', $_SESSION['key']);

		$this->setBlock($templateHandle, 'event', 'event_block');

		if(!$this->items->count()) {
			$this->setVar('event_block', '');
			return true;
		}

		$decor = new \Schoolbox\Notif\Message\Decorator(null);

		$j = 0;
		foreach($this->items as $item) {
			$decor->setMessage($item);
			$date = new Alaress_Date_Format($item->getTimestamp(), 'U');

			$this->setVar(array(
				'EVENT_HREF'     => $item->getBody()->getHref(),
				'EVENT_ICON'     => $item->getBody()->getIcon(),
				'EVENT_TIME'     => $date->getShort(),
				'EVENT_LONGTIME' => $date->getLong(),
				'EVENT_BODY'     => $decor->formatHtml(),
				'EVENT_DARK'     => $j++ % 2 ? 'dark' : '',
			));
			$this->parse('event_block', 'event', true);
		}

		return true;
	}

}
