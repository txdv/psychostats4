<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// The name of your web site.
$config['site_name'] = 'Testing';

// The domain name of your site. This is primarily used when sending email.
$config['domain_name'] = 'liche.net';

// Fully qualified URL for your stats website (including http:// prefix)
$config['base_url'] = 'http://liche.net/ps/';

// URL to your themes WITH trailing slash. This is used to fetch static content
// from themes.
$config['theme_url'] = '/ps/themes/';

// Absolute URL to the stock images WITH trailing slash. This is used to build
// static URL's to the images based on the img_dir path.
$config['img_url'] = '/ps/img/';

// Absolute URL to the stock flash charts (SWF)
$config['charts_url'] = '/ps/charts/';

// Absolute file path were themes are stored on the server
$config['theme_dir'] = dirname(FCPATH) . DIRECTORY_SEPARATOR . 'themes';

// Absolute root file path where the stock images are on the server. 
$config['img_dir'] = dirname(FCPATH) . DIRECTORY_SEPARATOR . 'img';

// Directory name for the different types of images within the img_dir path.
// These names should be a single directoy name and NOT a path.
$config['img_weapons_name'] = 'weapons';
$config['img_flags_name'] = 'flags';
$config['img_roles_name'] = 'roles';
$config['img_icons_name'] = 'icons';
$config['img_maps_name'] = 'maps';

// Absolute path where compiled templates are written to. Usually found within
// the BASEPATH of your stats site. Must be writable by the web server user.
$config['compile_dir'] = BASEPATH . 'cache';

// Default theme to use when a user has none selected.
$config['default_theme'] = 'default';

// Theme to use for the Administrator Control Panel (ACP)
$config['admin_theme'] = 'admin';

// Default view to load when the main home page is viewed. Example, if you want
// the main players list to be displayed you put 'players' here. Any relative
// URL will work.
$config['default_view'] = 'home';

// If true every time a user logs in a new password salt is generated for them.
// This increases DB security for users but not really necessary.
$config['login_regen_password_salt'] = true;

// list each gametype and associated modtype's that are available for stats.
$config['gametypes']['halflife'] = array('cstrike','tf');
$config['gametypes']['cod'] = array();
//$config['gametypes']['soldat'] = array();

$config['default_gametype'] = 'halflife';
$config['default_modtype']  = 'cstrike';

// URL fragments used for various pages.
// Change these if you override any of the stock pages within Psychostats.
$config['plr_url'] = 'plr';
$config['wpn_url'] = 'wpn';
$config['map_url'] = 'map';
$config['clan_url'] = 'clan';
$config['role_url'] = 'role';

// Overall header menu (order matters). The 'Label' is translated.
// Note: If you change the header_menu you must delete your compiled templates
// ['url'] = 'Label'
$config['header_menu']['home'] = 'Home';
$config['header_menu']['players'] = 'Players';
$config['header_menu']['clans'] = 'Clans';
$config['header_menu']['weapons'] = 'Weapons';
$config['header_menu']['maps'] = 'Maps';
$config['header_menu']['roles'] = 'Roles';
$config['header_menu']['achievements'] = 'Achievements';
$config['header_menu']['servers'] = 'Servers';
$config['header_menu']['admin'] = 'Admin'; // auto removed for non-admins

// default color gradient for percent bars (range: 0 - 100).
// Set only the indexes you want and the rest will be filled in automatically.
$config['pct_bar'][  0] = 'AAAAFF';
//$config['pct_bar'][ 25] = 'cccccc';
$config['pct_bar'][100] = '003355';

// Hard limit on search results. Set too high and some searches can take way
// too long or take up too much memory to process.
$config['limit_search'] = 1000;

// Should page output be compressed (gzip, deflate)? This is a per-client
// setting and is ignored for clients that do not support compression. It
// should be safe to always leave this enabled, unless you are embedding
// your stats into another page.
$config['enable_compression'] = false;

/*
|--------------------------------------------------------------------------
| Index File
|--------------------------------------------------------------------------
|
| Typically this will be your index.php file, unless you've renamed it to
| something else. If you are using mod_rewrite to remove the page set this
| variable so that it is blank.
|
*/
$config['index_page'] = '';

/*
|--------------------------------------------------------------------------
| URI PROTOCOL
|--------------------------------------------------------------------------
|
| This item determines which server global should be used to retrieve the
| URI string.  The default setting of "AUTO" works for most servers.
| If your links do not seem to work, try one of the other delicious flavors:
|
| 'AUTO'			Default - auto detects
| 'PATH_INFO'		Uses the PATH_INFO
| 'QUERY_STRING'	Uses the QUERY_STRING
| 'REQUEST_URI'		Uses the REQUEST_URI
| 'ORIG_PATH_INFO'	Uses the ORIG_PATH_INFO
|
*/
$config['uri_protocol']	= "PATH_INFO";

/*
|--------------------------------------------------------------------------
| URL suffix
|--------------------------------------------------------------------------
|
| This option allows you to add a suffix to all URLs generated by CodeIgniter.
| For more information please see the user guide:
|
| http://codeigniter.com/user_guide/general/urls.html
*/

$config['url_suffix'] = "";

/*
|--------------------------------------------------------------------------
| Default Language
|--------------------------------------------------------------------------
|
| This determines which set of language files should be used. Make sure
| there is an available translation if you intend to use something other
| than english.
|
*/
$config['language']	= "english";

/*
|--------------------------------------------------------------------------
| Default Character Set
|--------------------------------------------------------------------------
|
| This determines which character set is used by default in various methods
| that require a character set to be provided.
|
*/
$config['charset'] = "UTF-8";

/*
|--------------------------------------------------------------------------
| Enable/Disable System Hooks
|--------------------------------------------------------------------------
|
| If you would like to use the "hooks" feature you must enable it by
| setting this variable to TRUE (boolean).  See the user guide for details.
|
*/
$config['enable_hooks'] = FALSE;


/*
|--------------------------------------------------------------------------
| Class Extension Prefix
|--------------------------------------------------------------------------
|
| This item allows you to set the filename/classname prefix when extending
| native libraries.  For more information please see the user guide:
|
| http://codeigniter.com/user_guide/general/core_classes.html
| http://codeigniter.com/user_guide/general/creating_libraries.html
|
*/
$config['subclass_prefix'] = 'MY_';


/*
|--------------------------------------------------------------------------
| Allowed URL Characters
|--------------------------------------------------------------------------
|
| This lets you specify with a regular expression which characters are permitted
| within your URLs.  When someone tries to submit a URL with disallowed
| characters they will get a warning message.
|
| As a security measure you are STRONGLY encouraged to restrict URLs to
| as few characters as possible.  By default only these are allowed: a-z 0-9~%.:_-
|
| Leave blank to allow all characters -- but only if you are insane.
|
| DO NOT CHANGE THIS UNLESS YOU FULLY UNDERSTAND THE REPERCUSSIONS!!
|
*/
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';

/*
|--------------------------------------------------------------------------
| Enable Query Strings
|--------------------------------------------------------------------------
|
| By default CodeIgniter uses search-engine friendly segment based URLs:
| example.com/who/what/where/
|
| You can optionally enable standard query string based URLs:
| example.com?who=me&what=something&where=here
|
| Options are: TRUE or FALSE (boolean)
|
| The other items let you set the query string "words" that will
| invoke your controllers and its functions:
| example.com/index.php?c=controller&m=function
|
| Please note that some of the helpers won't work as expected when
| this feature is enabled, since CodeIgniter is designed primarily to
| use segment based URLs.
|
*/
$config['enable_query_strings'] = TRUE;
$config['controller_trigger'] 	= 'c';
$config['function_trigger'] 	= 'm';
$config['directory_trigger'] 	= 'd'; // experimental not currently in use

/*
|--------------------------------------------------------------------------
| Error Logging Threshold
|--------------------------------------------------------------------------
|
| If you have enabled error logging, you can set an error threshold to 
| determine what gets logged. Threshold options are:
| You can enable error logging by setting a threshold over zero. The
| threshold determines what gets logged. Threshold options are:
|
|	0 = Disables logging, Error logging TURNED OFF
|	1 = Error Messages (including PHP errors)
|	2 = Debug Messages
|	3 = Informational Messages
|	4 = All Messages
|
| For a live site you'll usually only enable Errors (1) to be logged otherwise
| your log files will fill up very fast.
|
*/
$config['log_threshold'] = 0;

/*
|--------------------------------------------------------------------------
| Error Logging Directory Path
|--------------------------------------------------------------------------
|
| Leave this BLANK unless you would like to set something other than the default
| system/logs/ folder.  Use a full server path with trailing slash.
|
*/
$config['log_path'] = '';

/*
|--------------------------------------------------------------------------
| Date Format for Logs
|--------------------------------------------------------------------------
|
| Each item that is logged has an associated date. You can use PHP date
| codes to set your own date formatting
|
*/
$config['log_date_format'] = 'Y-m-d H:i:s';

/*
|--------------------------------------------------------------------------
| Cache Directory Path
|--------------------------------------------------------------------------
|
| Leave this BLANK unless you would like to set something other than the default
| system/cache/ folder.  Use a full server path with trailing slash.
|
*/
$config['cache_path'] = '';

/*
|--------------------------------------------------------------------------
| Encryption Key
|--------------------------------------------------------------------------
|
| If you use the Encryption class or the Sessions class with encryption
| enabled you MUST set an encryption key.  See the user guide for info.
|
*/
$config['encryption_key'] = "";

/*
|--------------------------------------------------------------------------
| Session Variables
|--------------------------------------------------------------------------
|
*/
$config['sess_cookie_name']	= 'psid';
$config['sess_expiration']	= 7200;
$config['sess_encrypt_cookie']	= FALSE;
$config['sess_use_database']	= TRUE;
$config['sess_table_name']	= 'sessions';
$config['sess_match_ip']	= FALSE;
$config['sess_match_useragent']	= TRUE;
$config['sess_time_to_update'] 	= 300;

/*
|--------------------------------------------------------------------------
| Cookie Related Variables
|--------------------------------------------------------------------------
|
| 'cookie_prefix' = Set a prefix if you need to avoid collisions
| 'cookie_domain' = Set to .your-domain.com for site-wide cookies
| 'cookie_path'   =  Typically will be a forward slash
|
*/
$config['cookie_prefix']	= "";
$config['cookie_domain']	= "";
$config['cookie_path']		= "/";

/*
|--------------------------------------------------------------------------
| Global XSS Filtering
|--------------------------------------------------------------------------
|
| Determines whether the XSS filter is always active when GET, POST or
| COOKIE data is encountered
|
*/
$config['global_xss_filtering'] = FALSE;

/*
|--------------------------------------------------------------------------
| Output Compression
|--------------------------------------------------------------------------
|
| Enables Gzip output compression for faster page loads.  When enabled,
| the output class will test whether your server supports Gzip.
| Even if it does, however, not all browsers support compression
| so enable only if you are reasonably sure your visitors can handle it.
|
| VERY IMPORTANT:  If you are getting a blank page when compression is enabled it
| means you are prematurely outputting something to your browser. It could
| even be a line of whitespace at the end of one of your scripts.  For
| compression to work, nothing can be sent before the output buffer is called
| by the output class.  Do not "echo" any values with compression enabled.
|
*/
// DO NOT ENABLE! WILL INTERFERE WITH enable_compression above!
$config['compress_output'] = FALSE;
// DO NOT ENABLE! WILL INTERFERE WITH enable_compression above!

/*
|--------------------------------------------------------------------------
| Master Time Reference
|--------------------------------------------------------------------------
|
| Options are "local" or "gmt".  This pref tells the system whether to use
| your server's local time as the master "now" reference, or convert it to
| GMT.  See the "date helper" page of the user guide for information
| regarding date handling.
|
*/
$config['time_reference'] = 'gmt';


/*
|--------------------------------------------------------------------------
| Rewrite PHP Short Tags
|--------------------------------------------------------------------------
|
| If your PHP installation does not have short tag support enabled CI
| can rewrite the tags on-the-fly, enabling you to utilize that syntax
| in your view files.  Options are TRUE or FALSE (boolean)
|
*/
$config['rewrite_short_tags'] = FALSE;


/*
|--------------------------------------------------------------------------
| Reverse Proxy IPs
|--------------------------------------------------------------------------
|
| If your server is behind a reverse proxy, you must whitelist the proxy IP
| addresses from which CodeIgniter should trust the HTTP_X_FORWARDED_FOR
| header in order to properly identify the visitor's IP address.
| Comma-delimited, e.g. '10.0.1.200,10.0.1.201'
|
*/
$config['proxy_ips'] = '';


/* End of file config.php */
/* Location: ./system/application/config/config.php */
