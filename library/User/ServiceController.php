<?php

class User_ServiceController {
    private $sitePage;

    private $userHandler;

    private $groupHandler;

    private $externalUserHandler;

    private $imageHandler;

    public function __construct ($sitePage) {
        $this->setSitePage($sitePage);

        $this->setUserHandler( new User );
        $this->setGroupHandler( new Group );
        $this->setExternalUserHandler( new External_User );
        $this->setImageHandler( new Alaress_Image );
    }

    /**
     * @param Integer $userId
     * @param Integer $width
     * @param Integer $height
     * @return String
     */
    public function getPortrait ($externalUserId, $width = 200, $height = 300) {

        /* Check security permissions for images */
        $user = $this->getUserHandler()->getBySynergy($externalUserId);

        if ( $this->getGroupHandler()->isStaff() || //Allow all staff access to all photos
             $this->getGroupHandler()->isParentOfChild($user['uid']) || //Allow parents access to their childrens photos
       		 ( Cfg::get('protect_student_photo') == 0 && $this->getGroupHandler()->isStudent() && $this->getGroupHandler()->isStudent($user['uid']) ) || //If policy allows let students see other students photos
       		 ( Cfg::get('protect_teacher_photo') == 0 && $this->getGroupHandler()->isStaff($user['uid']) ) //If policy disabled allow access to staff photos
       	) {

            if ( !$portraitPath = $this->getExternalUserHandler()->getPortrait($externalUserId) ) {
                $portraitPath = WEB_root . '/images/core/placeholder.png';
            }
        }
        else
            $portraitPath = WEB_root . '/images/core/placeholder.png';

        $image = $this->getImageHandler()->imageResize($portraitPath, $height, $width, false);

        header("Content-Type: image/jpg");
        header("Content-Disposition: filename={$externalUserId}.jpg");

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
     * @param Group $groupHandler
     */
    public function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return User
     */
    public function getGroupHandler()
    {
        return $this->groupHandler;
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