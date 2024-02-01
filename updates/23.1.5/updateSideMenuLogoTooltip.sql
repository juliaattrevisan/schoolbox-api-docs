UPDATE config
SET
    description = "Upload an image at least 196 pixels wide that will display on the side menu. This will also act as your school logo for the news fallback image."
WHERE
    name = "skin_logo_large";
