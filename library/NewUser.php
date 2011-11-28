<?php
 
class User extends Model {
    public $id;

    public $username;

    public $password;

    public $groupId;

    public $title;

    public $firstname;

    public $lastname;

    public $emailPass;

    public $synergyId;

    public $campus;

    public $year;

    public $house;

    public $localEmail;

    public $altEmail;

    public $mailHandler;

    public $hash;

    public $key;

    public $eportfolioEnabled;

    public function setAltEmail($altEmail)
    {
        $this->altEmail = $altEmail;
    }

    public function getAltEmail()
    {
        return $this->altEmail;
    }

    public function setCampus($campus)
    {
        $this->campus = $campus;
    }

    public function getCampus()
    {
        return $this->campus;
    }

    public function setEmailPass($emailPass)
    {
        $this->emailPass = $emailPass;
    }

    public function getEmailPass()
    {
        return $this->emailPass;
    }

    public function setEportfolioEnabled($eportfolioEnabled)
    {
        $this->eportfolioEnabled = $eportfolioEnabled;
    }

    public function getEportfolioEnabled()
    {
        return $this->eportfolioEnabled;
    }

    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function setGroupId($groupId)
    {
        $this->groupId = $groupId;
    }

    public function getGroupId()
    {
        return $this->groupId;
    }

    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function setHouse($house)
    {
        $this->house = $house;
    }

    public function getHouse()
    {
        return $this->house;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setLocalEmail($localEmail)
    {
        $this->localEmail = $localEmail;
    }

    public function getLocalEmail()
    {
        return $this->localEmail;
    }

    public function setMailHandler($mailHandler)
    {
        $this->mailHandler = $mailHandler;
    }

    public function getMailHandler()
    {
        return $this->mailHandler;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setSynergyId($synergyId)
    {
        $this->synergyId = $synergyId;
    }

    public function getSynergyId()
    {
        return $this->synergyId;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setYear($year)
    {
        $this->year = $year;
    }

    public function getYear()
    {
        return $this->year;
    }

    /**
     * Format the user's name
     *
     * @param null $format
     * @return mixed|null|string
     */
    public function formatName($format = NULL){

        // Check for custom format
        if (empty($format)){
            $format = Cfg::get('name_format');
        }

        /* Replace tags */
        preg_match_all('|{\w+}|', $format, $tags);

        if (empty($tags) || empty($userInfo)) {
            return 'Unknown User';
        }

        foreach ($tags[0] as $tag) {
            $key = trim($tag, '{}');

            if (is_string($this->$key)) {
                $value = $this->$key;
            }
            else {
                $value = '';
            }

            $format = str_replace($tag, $value, $format);
        }

        if (trim($format)) {
            return $format;
        }
        else {
            return 'Unknown User';
        }
    }

}