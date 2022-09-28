#update all tour static references with the CDN replace check
UPDATE tour_step_modal set body = REPLACE(body, '/static/', '{CDN_PREFIX}/');
