UPDATE sportsfixtures_division sd
set deleted_at = (  SELECT deleted_at
                    FROM sportsfixtures_season
                    WHERE sportsfixtures_season.id = sd.season_id);