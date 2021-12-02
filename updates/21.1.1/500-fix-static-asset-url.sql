# Prior value was 'https://s3-ap-southeast-2.amazonaws.com/schoolbox-static/{site_version}'
# Problems were:
# * wrong bucket name
# * needs to be site_package-specific, not site_version-specific
UPDATE config SET value = 'https://s3-ap-southeast-2.amazonaws.com/sbx-static.schoolbox.cloud/{site_package}' WHERE name = 'site_static_asset_prefix';
