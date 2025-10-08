alter table user_jwt_id
    modify type enum ('api', 'qrcode', 'apitest') null;

