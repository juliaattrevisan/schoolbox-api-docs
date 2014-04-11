<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FeedController extends Component_Homepage_ControllerAbstract {

	/** @var \Schoolbox\Collection|\Schoolbox\Notif\Message\External\TargetedMessage[] */
	protected $messages;

	/**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $feed = new Feed();
        $this->messages = $feed->getFeed($this->getContext('id'), $this->getConfig('size'));
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

		if(!$this->messages->count()) {
			$this->setVar('event_block', '');
			return true;
		}

		$decor = new \Schoolbox\Notif\Message\Decorator(null);

		$j = 0;
		foreach($this->messages as $msg) {
			$decor->setMessage($msg);
			$date = new Alaress_Date_Format($msg->getTimestamp(), 'U');

			$this->setVar(array(
				'EVENT_HREF'     => $msg->getBody()->getHref(),
				'EVENT_ICON'     => $msg->getBody()->getIcon(),
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
