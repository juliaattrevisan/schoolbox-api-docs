UPDATE config SET value =
(
	CASE WHEN value IN ('sandbox-prod', 'sandbox-dev') THEN 'trial'
		WHEN value = 'vagrant' THEN 'dev'
        WHEN value = 'dev' THEN 'stg'
	END
)
WHERE name = 'site_type' AND value IN ('vagrant', 'dev', 'sandbox-prod', 'sandbox-dev')
