-- Align with component config instance's value length of 2048
-- because layouts inherit a name from their parent component's name

alter table tile_layout
    modify heading varchar(2048) not null;
