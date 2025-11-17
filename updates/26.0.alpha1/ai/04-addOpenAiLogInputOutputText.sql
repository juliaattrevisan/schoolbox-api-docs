ALTER TABLE openai_log
    ADD input_text TEXT NULL AFTER output_tokens,
    ADD output_text TEXT NULL AFTER input_text;

