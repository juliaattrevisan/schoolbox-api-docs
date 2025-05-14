# Set max_file_size to the server limit of 2GB if the current value exceeds that limit.
# 2147483648 = 2 x 1024 x 1024 x 1024 = 2GB.
UPDATE config
SET value = 2147483648
WHERE name = 'max_file_size'
AND value > 2147483648;
