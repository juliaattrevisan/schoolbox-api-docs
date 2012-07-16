<?php

class User_ServiceController {
    private $sitePage;

	/**
	 * Constructor
	 *
	 * @param $sitePage
	 */
    public function __construct($sitePage) {
        $this->setSitePage($sitePage);
    }

	/**
	 * Get a portrait for a particular user
	 *
	 * @param $externalUserId
	 * @param Integer $width
	 * @param Integer $height
	 */
    public function getPortrait ($externalUserId, $width = 200, $height = 300) {
		$portrait = new User_Portrait($externalUserId, $width, $height, new User(), new Group(), new External_User(), new Alaress_Image());

		$portrait->stream();
    }

    /**
     * @param SitePage $sitePage
     */
    public function setSitePage($sitePage)
    {
        $this->sitePage = $sitePage;
    }

    /**
     * @return SitePage
     */
    public function getSitePage()
    {
        return $this->sitePage;
    }
}