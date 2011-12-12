<?php

class User_ServiceController {
    private $sitePage;

    private $userHandler;

    private $externalUserHandler;

    private $imageHandler;

    public function __construct ($sitePage) {
        $this->setSitePage($sitePage);

        $this->setUserHandler( new User );
        $this->setExternalUserHandler( new External_User );
        $this->setImageHandler( new Alaress_Image );
    }

    /**
     * @param Integer $userId
     * @param Integer $width
     * @param Integer $height
     * @return String
     */
    public function getPortrait ($userId, $width = 200, $height = 300) {
        header("Content-Type: image/jpg");
        header("Content-Disposition: filename={$userId}.jpg");

        if ( !$portraitPath = $this->getExternalUserHandler()->getPortrait($userId) ) {
            $portraitPath = WEB_root . '/images/no_portrait.jpg';
        }

        $image = $this->getImageHandler()->imageResize($portraitPath, $height, $width, false);

        return imagejpeg($image);
    }

    /**
     * @param User $userHandler
     */
    public function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
     * @return User
     */
    public function getUserHandler()
    {
        return $this->userHandler;
    }

    /**
     * @param External_User $externalUserHandler
     */
    public function setExternalUserHandler($externalUserHandler)
    {
        $this->externalUserHandler = $externalUserHandler;
    }

    /**
     * @return External_User
     */
    public function getExternalUserHandler()
    {
        return $this->externalUserHandler;
    }

    /**
     * @param Image $imageHandler
     */
    public function setImageHandler($imageHandler)
    {
        $this->imageHandler = $imageHandler;
    }

    /**
     * @return Image
     */
    public function getImageHandler()
    {
        return $this->imageHandler;
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