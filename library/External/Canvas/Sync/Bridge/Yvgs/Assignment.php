<?php

//
///**
// * @copyright   Copyright 2023, Schoolbox Pty Ltd
// * @link        https://schoolbox.com.au/
// */
//
//declare(strict_types=1);
//
//namespace External\Canvas\Sync\Bridge\Yvgs;
//
//use Schoolbox\Assessment\Component\SubmissionBox;
//use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
//use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeLevelMapper;
//use Schoolbox\Mapper\Exception;
//use Schoolbox\User;
//use Schoolbox\UserMapper;
//
//class Assignment extends \External\Canvas\Sync\Bridge\Assignment
//{
//    private const GRADE_PASS_FAIL    = 'pass_fail';
//    private const GRADE_LETTER_GRADE = 'letter_grade';
//
//    private const GRADE_GROUPS =
//        [
//            self::GRADE_PASS_FAIL    => 'Canvas Completion',
//            self::GRADE_LETTER_GRADE => 'Canvas Grade Scheme',
//        ];
//
//    private const GRADES =
//        [
//            self::GRADE_PASS_FAIL    =>
//                [
//                    self::MARK_COMPLETE   => 100,
//                    self::MARK_INCOMPLETE => 0,
//                ],
//            self::GRADE_LETTER_GRADE =>
//                [
//                    'A+' => 89.5,
//                    'A'  => 79.5,
//                    'B+' => 74.5,
//                    'B'  => 69.5,
//                    'C+' => 64.5,
//                    'C'  => 59.5,
//                    'D+' => 54.5,
//                    'D'  => 49.5,
//                    'E+' => 44.5,
//                    'E'  => 39.5,
//                    'UG' => 0,
//                ],
//        ];
//
//    /**
//     * @param GradeLevelGroupMapper $gradeLevelGroupMapper
//     * @param GradeLevelMapper      $gradeLevelMapper
//     * @param UserMapper            $userMapper
//     * @return void
//     * @throws Exception
//     */
//    public function prepareLetterGrades(GradeLevelGroupMapper $gradeLevelGroupMapper, GradeLevelMapper $gradeLevelMapper, UserMapper $userMapper): void
//    {
//        $existing = false;
//        $admin    = $userMapper->findByUsername(User::USERNAME_SCHOOLBOX_ADMIN);
//        foreach (self::GRADE_GROUPS as $gradeGroupType => $gradeGroupName) {
//            try {
//                $gradeGroup = $gradeLevelGroupMapper->findOneByColumn('name', $gradeGroupName);
//                $existing   = true;
//            } catch (Exception $e) {
//                $gradeGroup = $gradeLevelGroupMapper->createModel()
//                                                    ->setName($gradeGroupName)
//                                                    ->setUpdatedBy($admin)
//                                                    ->setCreatedBy($admin)
//                                                    ->setCreatedAt(\Alaress_Date_Format::now());
//                $gradeLevelGroupMapper->save($gradeGroup);
//            }
//            $this->gradeGroups[$gradeGroupType] = $gradeGroup;
//        }
//        if ($existing) {
//            return;
//        }
//
//        foreach (self::GRADES as $gradeGroupType => $grades) {
//            $prevLowerBound = 100;
//            foreach ($grades as $caption => $lowerBound) {
//                $grade = $gradeLevelMapper->createModel()
//                                          ->setLowerBound($lowerBound)
//                                          ->setGroup($this->gradeGroups[$gradeGroupType])
//                                          ->setRawGrade(($prevLowerBound + $lowerBound) / 2)
//                                          ->setCaption($caption)
//                                          ->setUpdatedBy($admin)
//                                          ->setCreatedBy($admin)
//                                          ->setCreatedAt(\Alaress_Date_Format::now());
//                $gradeLevelMapper->save($grade);
//                $prevLowerBound = $lowerBound;
//            }
//        }
//    }
//
//    /**
//     * Returns a translated mark type
//     *
//     * @param string|null $gradingType
//     * @param float|null  $pointsPossible
//     * @return SubmissionBox\MarkType
//     */
//    public function getMarkType(?string $gradingType, ?float $pointsPossible): SubmissionBox\MarkType
//    {
//        switch ($gradingType) {
//            case 'points':
//            case 'gpa_scale':
//                // Schoolbox requires 'total-mark' scheme to have max-mark set > 0.
//                // It appears common to use 'gpa_scale', but leave points_possible as '0', often these appear to be peer reviewed.
//                // TODO discuss with client.
//                return $pointsPossible
//                    ? $this->markTypes[SubmissionBox\MarkType::SLUG_TOTAL]
//                    : $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
//            case 'pass_fail':
//            case 'letter_grade':
//                return $this->markTypes[SubmissionBox\MarkType::SLUG_LETTER];
//            case 'percent':
//                return $this->markTypes[SubmissionBox\MarkType::SLUG_PERCENT];
//            case 'not_graded':
//            case null:
//            default:
//                return $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
//        }
//    }
//
//    /**
//     * @param string|null $gradingType
//     * @return SubmissionBox\GradeLevel\Group\Model|null
//     */
//    public function getGradeGroup(?string $gradingType): ?SubmissionBox\GradeLevel\Group\Model
//    {
//        if (!$gradingType) {
//            return null;
//        }
//        return $this->gradeGroups[$gradingType] ?? null;
//    }
//
//    /**
//     * @inheritDoc
//     */
//    public function getMarkVisibility(bool $muted): int
//    {
//        // If muted, students cannot see grades left on assignment in Canvas
//        if ($muted) {
//            return IAssessable::MARK_VISIBILITY_PRIVATE;
//        }
//        return IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
//    }
//}
