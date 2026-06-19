-- [SA-22508] Deprecate the openai_log table: it has been replaced by ai_session
-- and ai_session_log. Renamed (not dropped) so data is retained for one release.
RENAME TABLE `openai_log` TO `_openai_log`;
