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
$cfg['application_code'] = "WAI";
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

$cfg['main_result'] = 'result';

//============== account =============================
// general
$cfg['account_default_group'] = 5;
// linked account autoregister
$cfg['account_autoregister']  = true;
// linked account facebook
$cfg['facebook_app_id'] = '';
$cfg['facebook_app_secret'] = '';
// linked account google
$cfg['google_client_id'] = '';
$cfg['google_client_secret'] = '';
// linked account twitter
$cfg['twitter_consumer_key'] = '';
$cfg['twitter_consumer_secret'] = '';

$cfg['upload_path'] = './upload/';

$cfg['email_smtp']        = 'smtp.gmail.com';
$cfg['email_account']     = '@gmail.com';
$cfg['email_password']    = '';
$cfg['email_smtp_port']   = '587';
$cfg['email_secure_type'] = 'tls';


?>
