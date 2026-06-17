INSERT INTO ai_session_log (
    ai_session_id,
    prompt,
    input_tokens,
    output_tokens,
    input_text,
    output_text,
    model,
    endpoint,
    log_format,
    response,
    return_code,
    error_message,
    created_at,
    created_by
)
SELECT
    session_id,
    prompt AS prompt,
    input_tokens,
    output_tokens,
    input_text AS input_text,
    output_text AS output_text,
    model AS model,
    endpoint AS endpoint,
    log_format,
    response AS response,
    return_code,
    error_message AS error_message,
    created_at,
    user_id AS created_by
FROM openai_log;
