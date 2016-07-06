<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use SitePage;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use \Schoolbox\SportsFixtures\Team\User\Mapper as TeamUserMapper;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use \Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @var UserMapper;
     */
    protected $userMapper;

    /**
     * @var FolderMapper;
     */
    protected $folderMapper;

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var TeamUserMapper
     */
    protected $teamUserMapper;

    /**
     * @var MatchMapper
     */
    protected $matchMapper;

    /**
     * @var VenueMapper
     */
    protected $venueMapper;

    /**
     * @var ItineraryItemMapper
     */
    protected $itineraryItemMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);
        $this->setUserMapper(UserMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setTeamUserMapper(TeamUserMapper::getInstance());
        $this->setItineraryItemMapper(ItineraryItemMapper::getInstance());
        $this->setVenueMapper(VenueMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Get current users role

        $matches = [];
        $teams = [];
        $sports = [];

        // Get the team we are looking at

        $team = $this->getTeamMapper()->findById($this->config["team"]["value"]);

        // Load all home and away matches for the team

        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $team->getId());
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $team->getId());

        // Whack all of the matches into an array

        /** @var Match $match */
        foreach ($home_matches as $match) {
            // Only want future matches
            if ($match->getStartAt()->isFuture()) {
                $matches[] = $match;

                // Load sport if we haven't already
                if (!isset($sports[$match->getDivision()->getSport()->getId()])) {
                    $sports[$match->getDivision()->getSport()->getId()] =
                        $this->getSportMapper()->findById(
                            $match->getDivision()->getSport()->getId()
                        )->getName();
                }

                // Load team if we haven't already
                if (!isset($teams[$match->getAwayTeam()->getId()])) {
                    $teams[$match->getAwayTeam()->getId()] =
                        $this->getTeamMapper()->findById(
                            $match->getAwayTeam()->getId()
                        )->getName();
                }
                if (!isset($teams[$match->getHomeTeam()->getId()])) {
                    $teams[$match->getHomeTeam()->getId()] =
                        $this->getTeamMapper()->findById(
                            $match->getHomeTeam()->getId()
                        )->getName();
                }
            }
        }

        foreach ($away_matches as $match) {
            // Only want future matches
            if ($match->getStartAt()->isFuture()) {
                $matches[] = $match;

                // Load sport if we haven't already
                if (!isset($sports[$match->getDivision()->getSport()->getId()])) {
                    $sports[$match->getDivision()->getSport()->getId()] =
                        $this->getSportMapper()->findById(
                            $match->getDivision()->getSport()->getId()
                        )->getName();
                }

                // Load team if we haven't already
                if (!isset($teams[$match->getAwayTeam()->getId()])) {
                    $teams[$match->getAwayTeam()->getId()] =
                        $this->getTeamMapper()->findById(
                            $match->getAwayTeam()->getId()
                        )->getName();
                }
                if (!isset($teams[$match->getHomeTeam()->getId()])) {
                    $teams[$match->getHomeTeam()->getId()] =
                        $this->getTeamMapper()->findById(
                            $match->getHomeTeam()->getId()
                        )->getName();
                }
            }
        }

        // Sort matches by date order
        usort($matches, function($a, $b) {
            /** @var Match $a */
            /** @var Match $b */
            return ($a->getStartAt()->compare($b->getStartAt()));
        });

        $data = [
            'matches' => $matches,
            'sports' => $sports,
            'teams' => $teams
        ];

        return $this->renderTwigTemplate(
            $templateHandle,
            '@sportsfixturesHomepage/component.twig',
            array_merge($data, [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName()
            ])
        );
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param  TeamMapper $teamMapper
     * @return $this
     */
    public function setTeamMapper(TeamMapper $teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return MatchMapper
     */
    public function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
    }

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
    }

    /**
     * @return TeamUserMapper
     */
    public function getTeamUserMapper()
    {
        return $this->teamUserMapper;
    }

    /**
     * @param  TeamUserMapper $teamUserMapper
     * @return $this
     */
    public function setTeamUserMapper(TeamUserMapper $teamUserMapper)
    {
        $this->teamUserMapper = $teamUserMapper;
        return $this;
    }

    /**
     * @return ItineraryItemMapper
     */
    public function getItineraryItemMapper()
    {
        return $this->itineraryItemMapper;
    }

    /**
     * @param ItineraryItemMapper $itineraryItemMapper
     */
    public function setItineraryItemMapper($itineraryItemMapper)
    {
        $this->itineraryItemMapper = $itineraryItemMapper;
    }

    /**
     * @return VenueMapper
     */
    public function getVenueMapper()
    {
        return $this->venueMapper;
    }

    /**
     * @param VenueMapper $venueMapper
     */
    public function setVenueMapper($venueMapper)
    {
        $this->venueMapper = $venueMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param  FolderMapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }
}