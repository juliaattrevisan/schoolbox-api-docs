INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `type_id`, `value`, `editable`)
VALUES ('rubric_show_points', 'Show Points on Rubrics',
        'This will remove weighting point values on rubrics.  Note disabling this will remove any ability for users to determine weightings and should only be done if you want to reduce transparency about grading or weights are never relevant',
        3, 15, 1, 'true');
