<?php
$cfg['dbtype'] = "mysql"; //mysql, pgsql
$cfg['dbhost'] = "127.0.0.1";
$cfg['dbuser'] = "root";
$cfg['dbpass'] = "";
$cfg['dbname'] = "";
$cfg['dbport'] = "3306";

//Application Configuration
$cfg['project_name'] = "Simple Framework";
$cfg['copyright'] = "WebAppId";
$cfg['application_code'] = "WebAppId";
$cfg['application_version'] = "4.0";
$cfg['application_title'] = "News Feed";
$cfg['domain_name'] = "localhost";

//session setting
$cfg['session_name'] = "sf";
$cfg['session_expire'] = "10";

//language default setting
$cfg['languageId'] = "1";

//user default
$cfg['user_default'] = "nobody";

//page default
$cfg['default_page'] = "homeViewHomeJson";

//url_type
$cfg['application_type'] = "modular"; //modular,cms

//list paging default
$cfg['itemViewed'] = 20;

// SYSTEM
$cfg['basedir'] = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);

//============== account =============================
// general
$cfg['account_default_group'] = 5;
// linked account autoregister
$cfg['account_autoregister']  = TRUE;
// linked account facebook
$cfg['facebook_app_id'] = '1547817185539558';
$cfg['facebook_app_secret'] = 'b947a3f6436d86f0a6c68cdbfd3e1607';
// linked account google
$cfg['google_client_id'] = '';
$cfg['google_client_secret'] = '';
// linked account twitter
$cfg['twitter_consumer_key'] = '';
$cfg['twitter_consumer_secret'] = '';
?>
