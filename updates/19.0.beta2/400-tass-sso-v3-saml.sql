UPDATE config SET options = '1;Version 1 only:2;Version 2 only:3;Version 2 with fallback to Version 1:4;Version 3 (SAML)' WHERE name = 'external_tass_sso_token_generation_strategy';
