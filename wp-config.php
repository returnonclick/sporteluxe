<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'sporteluxe');

/** MySQL database username */
define('DB_USER', 'sporteluxe');

/** MySQL database password */
define('DB_PASSWORD', 'theBrazilian3');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '+V69X|h)M}.g)I5Mev^^mk/>Q9Dhg; 6x)3aqWVb?DHLqnqr:]h2`z/)dXBCFM.z');
define('SECURE_AUTH_KEY',  '*wgf~ D-~:+`MdqE?84,^_~nab`gt2+2BD9*Rl&&.)97{)O0wmKW)Jd+RZ-.Pg&n');
define('LOGGED_IN_KEY',    '4ABnSAn;#NM3xiKm6 ,emMdu>+x%N=~?R2!YgUggXPV9MVE+)`V|$j6bnBY/wh36');
define('NONCE_KEY',        'nxW|-FZgnfB7-BQ1Qk5/3*`!kg-/LLuF3}8m1&B/z*o|jb_fEEAcWZgg?uR;>D?,');
define('AUTH_SALT',        'cwW8O@dnw40=m-xI+@)s$1Y7zsogJjGs<a9JFOR@Hx!|e>C;#,, UJGK+S2F`|;m');
define('SECURE_AUTH_SALT', '*b6o2~izN@Q0p[g+kHc!/1|ANQ9{ow$5b%i}t,U`?purJKfdiwE(4?zKkwye*Tc>');
define('LOGGED_IN_SALT',   'PDilvONbR`%{%d70<=WrxYkgw>=`+)Mt<Re,1EAcFH,|6:$6tW}4^yD12WL7]W~X');
define('NONCE_SALT',       'mF*~=t>BInl?|b8`rA|9.*x1a8_HD?`8si9np]FAbJwf<%Gw@jt5&E-vh`i{JcP3');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'sl_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
