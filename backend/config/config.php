<?php

/*
|--------------------------------------------------------------------------
| DATABASE CONFIG
|--------------------------------------------------------------------------
*/
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'u851104905_business');
// define('DB_USER', 'u851104905_business');
// define('DB_PASS', 'Niruban@@New1712');
define('DB_HOST', 'localhost');
define('DB_NAME', 'business_community');
define('DB_USER', 'root');
define('DB_PASS', '');

/*
|--------------------------------------------------------------------------
| JWT CONFIG
|--------------------------------------------------------------------------
*/
define('JWT_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET');
define('JWT_TTL', 86400);
define('JWT_ISSUER', 'business_community');

/*
|--------------------------------------------------------------------------
| APP CONFIG (REQUIRED)
|--------------------------------------------------------------------------
*/
//define('APP_URL', 'https://qnexus.in/business_community');
define('APP_URL', 'http://localhost/business_community');
/*
|--------------------------------------------------------------------------
| TIMEZONE FIX (IMPORTANT)
|--------------------------------------------------------------------------
*/
date_default_timezone_set('UTC');
