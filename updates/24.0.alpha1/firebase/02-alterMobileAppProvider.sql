# Allow Firebase as a provider.
alter table mobile_app modify provider enum ('APNS', 'GCM', 'Digistorm', 'Firebase') charset ascii not null;

