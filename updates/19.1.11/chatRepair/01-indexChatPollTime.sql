-- WHERE queries are performed on chat_poll.time for determining who's in a room.
-- Index this column.
create index chat_poll_time_index on chat_poll (time);
