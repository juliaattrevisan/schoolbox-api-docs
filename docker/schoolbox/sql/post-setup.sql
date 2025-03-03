UPDATE config SET value='tcp://redis-storage:6379' WHERE name='redis_url';
UPDATE config SET value='tcp://redis-cache:6379' WHERE name='redis_ephemeral_url';
UPDATE config SET value='tcp://redis-storage:6379' WHERE name='taskd_redis_url';
UPDATE config SET value='rabbitmq' WHERE name='amqp_host';
UPDATE config SET value='http://schoolbox-gotenberg:3000' WHERE name='gotenberg_host';
