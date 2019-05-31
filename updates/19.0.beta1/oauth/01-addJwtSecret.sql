insert into config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required
)
VALUES (
    'oauth_jwt_secret',
    '',
    'oAuth JWT Secret',
    'oAuth JWT Secret',
    2, -- textarea
    '',
    21, -- SAML? will probably change this
    'true',
    'false',
    'false'
);
