-- Foundation tooltip doesn't like content containing html tags like <head> <script>
-- can get the same outcome by using the htmltentity instead, to bust up the synatx

UPDATE `config`
SET `description` = 'Insert custom JavaScript if required for your instances\' &lt;head> section. This will run all all pages, excluding the admin area. Please include &lt;script> tags.'
WHERE `name` = 'site_header_scripts';
