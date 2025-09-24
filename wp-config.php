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
define( 'DB_NAME', 'wordpress_ai_test' );

/** Database username */
define( 'DB_USER', 'wp_user' );

/** Database password */
define( 'DB_PASSWORD', 'wp_password' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define('AUTH_KEY',         'FzuyIkxx@z4CX9X^sg{[FoiL+&bD{w*eB?VW0 y}#;9r@v-MEwKf|7@t={7nP>)V');
define('SECURE_AUTH_KEY',  ' ek2Dm/Yn%oM:VH^OkH n|]n]72pFTql6=@F{m-lS@g-t8)iaSJS&|p,.m?s$8+@');
define('LOGGED_IN_KEY',    '-h[>Qzx4w>Q9p2HOOv3,mK(Nx<Q7HGrw^MW+m)3sv*roAvC=fYT7qTOv1&HE5</,');
define('NONCE_KEY',        ',c3j?bkKyDjDeYoI>W<P@@a#?-?}QZ{!x2>%qp(,JS%W6E?#Jf*xBp-CRbB3oQnS');
define('AUTH_SALT',        ')mMw{{5#M.fM^YmF^X |B-s|IVS7Ty[m>-u?`avvc+3|v)L7qYn|4*FwhMBI|f2m');
define('SECURE_AUTH_SALT', '|ANRtsL+$v|VGZ(Qf-*~yQtxl_W=mM7.;R:lW;+8@:[L0TCl]1#-`{LVLa6QJB$7');
define('LOGGED_IN_SALT',   'D#^OX?M%rH[a],aW5y|sgG_CnP;wsi{ ?9hb$u+8vLeVOV<GFj!qNI$WdoFeq*G-');
define('NONCE_SALT',       'vfE}Z#EB6KuAs|-I_FsmV+k^p&Yri,KM6Rq,0-[L->0LVhq~>&$LQ+CwQRr$V%sz');

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
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// AI自動入力機能のデバッグ設定
define( 'GI_AI_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
