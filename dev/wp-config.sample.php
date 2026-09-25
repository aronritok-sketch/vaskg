<?php
// Helyi fejlesztői wp-config. Másold a WordPress core mappájába wp-config.php néven,
// és írd át a REPO útvonalat és az adatbázis adatait.
define('REPO', '/path/to/vasskisgep');

define('DB_NAME', 'vasskisgep');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = 'vssksgp_';

define('WP_CONTENT_DIR', REPO.'/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8080/wp-content');
define('WP_HOME', 'http://localhost:8080');
define('WP_SITEURL', 'http://localhost:8080');

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENVIRONMENT_TYPE', 'local');
define('DISABLE_WP_CRON', true);

// A telepítő lépések helyben csak WP-CLI-ből futnak (wp vasskisgep setup).
define('VASSKISGEP_SETUP_MANUAL', true);

// E-mailek küldés helyett a wp-content/mail.log fájlba.
$GLOBALS['wp_filter']['pre_wp_mail'][10][] = [
	'function' => function($return, $atts){
		$to = is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to'];
		file_put_contents(WP_CONTENT_DIR.'/mail.log', date('c').' TO: '.$to.' | '.$atts['subject']."\n".wp_strip_all_tags($atts['message'])."\n-----\n", FILE_APPEND);
		return true;
	},
	'accepted_args' => 2
];

foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key) define($key, 'local-dev-'.$key);

if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
require_once ABSPATH.'wp-settings.php';
