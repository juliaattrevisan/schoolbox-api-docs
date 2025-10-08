-- Add Field Description columns
alter table lesson_plan_template_field
    add description TEXT charset utf8mb4 null after body;

alter table lesson_plan_field
    add description TEXT charset utf8mb4 null after body;

