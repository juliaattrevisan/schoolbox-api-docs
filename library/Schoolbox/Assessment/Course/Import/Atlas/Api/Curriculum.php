<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Schoolbox\Collection;

/**
 * Object representation of a curriculum from Atlas Rubicon.
 */
class Curriculum
{
    /**
     * "id": 20,
    "title": "Health K",
    "subject": "Health",
    "grade": "Kindergarten",
    "school": "OS/MAISA",
    "description": "<p><strong>Health K</strong></p><p></p><p><strong>Overview</strong></p><p>Utilizing the Michigan Model for Health kindergarten curriculum, children learn accurate and current information on the following units of study: Social and Emotional Health, Safety and Violence Prevention, Healthy Eating &amp; Physical Activity, Tobacco, Alcohol &amp; Other Drugs Prevention, and Personal Health &amp; Wellness. Content covered in each unit can be found in the image below.</p><p></p><p><strong>Rationale</strong></p><p>Health education involves improving and/or maintaining positive health behaviors and reducing health risk behaviors, as the behaviors a person selects impact the person’s health status. The Michigan Model for Health has merged several theories of behavior change into an Adapted Health Belief Model consisting of four factors that contribute to behavior change: knowledge, skills, self-efficacy, and environmental support. Behavior change is more likely to occur if these four factors are included in a health education program. Omitting any of these four factors lower the chances of reaching the goal of improving or maintaining positive health behaviors and reducing health risk behaviors. These factors have guided the development of this curriculum.</p><p></p><p>Seven out of eight required National Health Education Standards focus on skills. The Michigan Health Education Standards and the Grade Level Content Expectations emphasize skills with the same level of importance.</p><p></p><p>The skills our young people need to be contributing members of society, successful employees, and nurturing family members mirror the skills young people need to maintain and improve their health. This curriculum, addresses health content and essential health skills while reaching beyond health to emphasize and reinforce the essential skills the children need for overall well-being.</p><p></p><p><strong>Scope and Sequence</strong></p><p>There are 21 lessons included in the Kindergarten Health curriculum, which are approximately 15 minutes in length. Lessons within each unit are most effective when used sequentially however, units may be taught in any order throughout the year. Many teachers report that using the social and emotional health unit early in the fall assists in setting expectations for behavior in the classroom.</p><p></p><p><strong>Alignment</strong></p><p>The lessons in the course align with the National and Michigan Health Education Standards and meet the Grade Level Content Expectations for Health in kindergarten.</p><p></p><p>For more information on local training opportunities, materials, and the evidence-based curriculum, please contact Christina Harvey at christina.harvey@oakland.k12.mi.us or 248-209-2413.</p>",
    "last_updated": "2010-03-10 15:24:42.107",
    "map_type": "",
     */

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var string
     */
    private $grade;

    /**
     * @var string
     */
    private $school;

    /**
     * @var string
     */
    private $description;

    /**
     * @var \Alaress_Date_Format
     */
    private $lastUpdated;

    /**
     * @var string
     */
    private $mapType;

    /**
     * @todo write teacher object
     * @var Collection
     */
    private $teachers;

    /**
     * @todo write unit object
     * @var Collection
     */
    private $units;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->teachers = new Collection();
        $this->units    = new Collection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return static
     */
    public function setSubject(string $subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getGrade(): string
    {
        return $this->grade;
    }

    /**
     * @param string $grade
     * @return static
     */
    public function setGrade(string $grade)
    {
        $this->grade = $grade;
        return $this;
    }

    /**
     * @return string
     */
    public function getSchool(): string
    {
        return $this->school;
    }

    /**
     * @param string $school
     * @return static
     */
    public function setSchool(string $school)
    {
        $this->school = $school;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getLastUpdated(): \Alaress_Date_Format
    {
        return $this->lastUpdated;
    }

    /**
     * @param \Alaress_Date_Format $lastUpdated
     * @return static
     */
    public function setLastUpdated(\Alaress_Date_Format $lastUpdated)
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * @return string
     */
    public function getMapType(): string
    {
        return $this->mapType;
    }

    /**
     * @param string $mapType
     * @return static
     */
    public function setMapType(string $mapType)
    {
        $this->mapType = $mapType;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    /**
     * @param Collection $teachers
     * @return static
     */
    public function setTeachers(Collection $teachers)
    {
        $this->teachers = $teachers;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getUnits(): Collection
    {
        return $this->units;
    }

    /**
     * @param Collection $units
     * @return static
     */
    public function setUnits(Collection $units)
    {
        $this->units = $units;
        return $this;
    }
}
