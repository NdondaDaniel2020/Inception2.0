<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'organic' );

/** Database username */
define( 'DB_USER', 'nmatondo' );

/** Database password */
define( 'DB_PASSWORD', 'nmatondo@student.42luanda.com' );

/** Database hostname */
define( 'DB_HOST', 'mariadb' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'MaY,N2ZQm<EZ*LR`xJQeRXb+K[$N(}R&xv!S|CK-AJ^`Aivmq1UUz4Fx?(r$V*LP' );
define( 'SECURE_AUTH_KEY',  'r~JuOGxp$WRC{1w%R(cOg:&[|TiMin A[)f:DFug9O1[e@.MYUH@LWJ93q*Uw+rw' );
define( 'LOGGED_IN_KEY',    'Sa32kfb?.vxS+XjagFs[qx_}L5hcqOY:Sy]XtMkf!D+Q@t_l:d>]zHmooA9d8Yrs' );
define( 'NONCE_KEY',        '_=mxE_N!S5Pm]D1%4thhR7c+kr67#?/TyK~K1{0y,cq9w*R_]u=r{B%2ZtF{Nqip' );
define( 'AUTH_SALT',        '|(/mQ(%tJ];VH79tCT`}y{Aj~1QFm5lO3RI?X@J<%m+/n253BQwCc&rOr/>QDK=:' );
define( 'SECURE_AUTH_SALT', '(w]!,r12%73+a+B bcA?8~/_Kdi#p.,/p)?Jp*MKCnxlC=X3Qy~0)`N]j|iJ.6a2' );
define( 'LOGGED_IN_SALT',   '*69>!8~;X~ lo}K+_)bn)sa02V9/<#``tziT xadbsXN.6&ItYk>/V3(A6 C`v~S' );
define( 'NONCE_SALT',       ';9hPOj+yA@L7X[.k9Ajs-=/mkO{K||i?XjX_!A@EO[:F+`#uN9~67UJ[{C3Px<72' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'og_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

/** Force WordPress URLs */
define( 'WP_HOME', 'https://' . (getenv('DOMAIN_NAME') ?: 'nmatondo.42.fr') );
define( 'WP_SITEURL', 'https://' . (getenv('DOMAIN_NAME') ?: 'nmatondo.42.fr') );

/* Redis Cache Configuration */
define('WP_CACHE', true);
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', 'REDIS_PASSWORD_PLACEHOLDER');

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
