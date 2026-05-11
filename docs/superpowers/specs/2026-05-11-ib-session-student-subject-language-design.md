# IB Session Student Subject Language Override Design

## Context

IB registration session candidates currently return each student's matched IB group subjects with the default subject languages from `ib_group_subject_languages`. The new `ib_registration_session_student_subject_language` table stores per-student language overrides for a subject within a registration session.

The candidates page needs subject data visible when it first loads, so this design keeps subjects in the initial response and focuses on batched loading rather than lazy per-student loading.

## Goal

When returning students for an IB registration session, each subject should show the student's overridden language when one exists. If no override exists for that student and subject, the response should continue to show the default subject languages.

## Data Flow

The existing candidates endpoint remains the entry point:

1. `InternationalBaccalaureate\Controller::getRegistrationSessionStudents()` loads students for the session.
2. `Registrations\Sessions\Student\Mapper::findStudentsForSession()` builds each student's default matched IB group subjects.
3. The student mapper loads all subject-language overrides for persisted session-student IDs in one batched lookup.
4. For each override, the mapper finds the matching `GroupSubjectModel` by `group_subject_id` and replaces that subject's language collection with a single overridden language.
5. `Registrations\Sessions\Student\Payload::build()` serializes the effective subjects and languages.

## Behaviour

- A persisted session student with an override for a subject receives one language for that subject: the overridden language.
- A persisted session student without an override for a subject keeps the existing default subject language list.
- A derived student without an `ib_registration_session_student` row cannot have override rows yet, so defaults are returned.
- An override whose `group_subject_id` is not present in the student's matched subject list is ignored for the session payload.
- Existing missing-details and status payload behaviour is unchanged.

## Implementation Shape

The override should be resolved in the student mapper after default subject matching is complete. This keeps the fallback rules close to the data-loading layer and lets the payload continue serializing the effective `GroupSubjectModel` collection without needing separate override logic.

The existing `Registrations\Sessions\Student\Subject\Language\Mapper` should expose a batched lookup by registration session student IDs. The student mapper should call that lookup once per session result set, avoiding one query per student.

Lazy loading subjects per student is not part of this design because the page requires subjects to be visible on initial load. If candidate lists become paginated or row-expanded in a later change, a separate on-demand subject endpoint can be considered then.

## Testing

Unit coverage should verify:

- Override rows replace the default language list for matching persisted students and subjects.
- Subjects without override rows keep their default language list.
- Derived students without persisted IDs are not included in the override lookup and keep defaults.
- Override rows for subjects not present in the student's current matched subjects are ignored.

Existing payload tests should only need updates if the effective subject language shape changes in test fixtures.
