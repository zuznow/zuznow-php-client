zuznow-php-client
=================

zuznow php client

In order to activate Zuznow Client you have to:
1. Install the files under MOB folder
2. Configure the clinet by editing mob_config.php
3. Activate the clinet by adding mob_filter.php to every php document usin auto_prepend_file command at php.ini

Configuring Zuznow Client:
$server_url - The URL of Zuznow Mobilization Server (ZMS)
$domain_id - The ID of the required domain
$api_key - The API Key of the required domain
$cache_type - The type of API cache, can be one of:
1. anonymous
2. personalized
3. none
$cache_ttl - Time to Live for the API cache in the ZMS in minutes
