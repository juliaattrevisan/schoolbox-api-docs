# -- Set 'source' column in 'event' table to NOT NULL
# -- to ensure every event has a defined source.
#
# alter table event
#     modify source enum ('calendar', 'booking') not null;
#
