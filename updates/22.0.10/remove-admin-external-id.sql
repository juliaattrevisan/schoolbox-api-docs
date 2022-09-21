-- make sure that external id is not set for alaress user
UPDATE `user` SET `synergy_id` = null WHERE `username` = 'alaress';
