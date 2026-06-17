# IB Registration API Response Log Helper Design

## Context

IB candidate registration uses `RegisterCandidatesTool` to submit candidate-number and session-registration requests to IBIS. A new `ib_registration_api_response_log` table stores one row per IBIS API response item, keyed to the local `ib_registration_sessions.id` and, where available, the processed `ib_registration_session_student.id`.

The next step is to add a small backend helper layer so registration attempts can save response rows consistently and the admin candidate list can return the latest failure message for each failed candidate.

## Goals

- Save IBIS candidate-number and session-registration response items to `ib_registration_api_response_log`.
- Read the latest error row for each `ib_registration_session_student.id` in a registration session.
- Add the latest candidate registration error to the existing candidate-list API response so the frontend can display why registration failed.
- Keep pre-request setup/build failures out of this table unless a request and `msg_id` exist.

## Non-Goals

- Do not add a separate log viewer endpoint.
- Do not store raw SOAP XML or HTTP status codes.
- Do not denormalise `user_id` into the log table.
- Do not change the existing candidate registration state machine.

## Proposed Structure

Add these classes under `Schoolbox\IB\Registrations\Api\ResponseLog`:

- `Model`: represents one row in `ib_registration_api_response_log`.
- `Mapper`: maps table columns and provides persistence/read queries.
- `Helper`: provides intent-level save/read methods for IB registration workflows.

The model should define constants for:

- `REQUEST_TYPE_REGISTER_CANDIDATES = 'register_candidates'`
- `REQUEST_TYPE_REGISTER_SESSIONS = 'register_sessions'`
- `STATUS_SUCCESS = 'success'`
- `STATUS_ERROR = 'error'`

## Saving Logs

`RegisterCandidatesTool` should obtain the response log mapper through `MapperFactory` and create a helper where it processes candidates.

The helper should expose methods similar to:

- `saveCandidateResponse(StudentModel $student, string $msgId, array $candidateResponse): void`
- `saveSessionResponse(StudentModel $student, string $msgId, array $sessionResponse): void`
- `saveRequestError(int $registrationSessionId, ?StudentModel $student, string $msgId, string $requestType, Throwable|string $error): void`

For parsed response rows:

- `registration_session_id` comes from `$student->getRegistrationSession()->getId()`.
- `registration_session_student_id` comes from `$student->getId()`.
- `msg_id` comes from the request object.
- `request_type` identifies the IBIS operation.
- `status` is `success` when the parsed response item has `successful === true`; otherwise `error`.
- `message` stores the response `text` value or an error summary.

For request-level exceptions after a request has been built, save an error row with the same `msg_id`. If no student-specific context exists, leave `registration_session_student_id` null.

## Reading Logs

The mapper should provide:

- `findLatestErrorsForSession(int $registrationSessionId): array`

This should return the latest error per non-null `registration_session_student_id`, ordered by `created_at DESC, id DESC`. Session-level rows where `registration_session_student_id IS NULL` should not be merged into candidate rows for this first version.

The helper should expose:

- `getLatestErrorMessagesByStudentId(int $registrationSessionId): array`

This returns an associative array keyed by `registration_session_student_id`, with string error messages as values.

## Admin API Integration

`Controller::getRegistrationSessionStudents()` should load latest error messages once for the session and pass the relevant message into the student translator.

`RegistrationSessionStudent\Translator\ModelToArray` should add:

- `registrationError: string|null`

The frontend can display this field when a candidate has `STATUS_FAILED`.

## Error Handling

Logging failures should not mask or reverse the candidate registration outcome. If saving a log row fails, the tool should continue to mark the candidate according to the IBIS result and write the logging failure to the normal application logger.

The table requires `msg_id`, so failures before a request object exists remain normal CLI/application logs rather than response-log rows.

## Testing

- Unit test model/mapper mapping for all table columns.
- Unit test helper conversion of candidate and session response arrays into log rows.
- Unit test latest-error lookup when one student has multiple failed attempts.
- Update `ModelToArrayTest` to verify `registrationError`.
- Add focused `RegisterCandidatesTool` coverage if the existing test setup can exercise request/response processing without a broad integration fixture.
