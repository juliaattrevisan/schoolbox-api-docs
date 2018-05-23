INSERT INTO `config` (
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
) VALUES (
    'rubicon_atlas_api_token',
    'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6Ijk3NDNkNjVkNzRkN2Q0MTE0MmIxYmNmZjQ0NDg3YTZhNjYyOGFmYjFjNTVjNWEwZjAyMGQ0MzBkNGIwNjRkNDg1MGUzZDliZDgwYmZlNWQyIn0.eyJhdWQiOiIxIiwianRpIjoiOTc0M2Q2NWQ3NGQ3ZDQxMTQyYjFiY2ZmNDQ0ODdhNmE2NjI4YWZiMWM1NWM1YTBmMDIwZDQzMGQ0YjA2NGQ0ODUwZTNkOWJkODBiZmU1ZDIiLCJpYXQiOjE0ODQ4NTg3OTMsIm5iZiI6MTQ4NDg1ODc5MywiZXhwIjoxODAwMzkxNTkzLCJzdWIiOiIyIiwic2NvcGVzIjpbInNjaG9vbGJveCJdfQ.Vv4GBe5NDg9nQd3rZnORMa_GW9RUSi3mXgTG3mJEzxq99GZXgiKtL4CBdeb_m_C69rflV1DBNNGBtmYGgQ1i1neNANExgRqg6hrhk3lNBWv9uJ8HWpvoB8B61eHMJJMwc247t_k9iFLmmMsvr3wuxeOY_7ZYKrjcVuIceAbmT9fBCizIrKmZzAHsgT3NmXnklFz8l2srTaUvbSnvCaOnax4YwPoFUwZdFMQVy4uec7iZyy-kHIjOrlOPtCPL6Q7QxD_0h_-awkSvedhz4XuiHmdtnbUD10TCvsb1VJbm81Cmu_p444PAF3JpadtMcom2h-A74SwjIQxC6aduCZwr8gC-MZott1pBJPzmQ9dN4AlmIrwZttC4gk34faEiWIK8FSqaMeFMK2MLm2ums3Wd_onjq5goYRRXWR9sx4APdPv2DQElMZFXziS8t_IvSi_QnXre7IDpibFGZQ8aUNlkCqyq5ytu21P8NK2mgdxyAYVzmaR9wCmSWn-ogo6SO2x6v633ejIK6OuPRZ5657BygjuS6s3NRomxR5tCw9zlqMyRrXxXIxA5IvvyyUO1x7EhfBatBu3n7ILFyN91D1Eh7_qhMIzShuV0F7X_L0q2CKqgRJKStnjtMraG-oEDfxKL7PowUutT5tCWStKo9YMNiD2Ylk-s_CQvsqqLP2A0ZqE',
    'Rubicon Atlas API Token',
    'Rubicon Atlas API token. Required for retrieving curriculum plans stored in a Rubicon Atlas instance.',
    2, -- textarea
    '',
    10, -- 3rd party services
    'true',
    'false',
    'false'
);
