<?php
require 'vendor/autoload.php';
require 'rb.phar';
// header('Content-type: application/json');

define('TUITION_PING_TIME_MINUTES', 15);
define('PROGRESS_ACTIVE_TIME_MINUTES', 90);
define('PROGRESS_MAX_AMOUNT_MINUTES', 20);
define('PROGRESS_DEFAULT_AMOUNT_MINUTES', 5);


$app = new \Slim\Slim(array(
    'debug' => true,
    'log.enable' => true,
    'log.level' => \Slim\Log::DEBUG
));

$app->contentType("application/json");

// require 'push/push.php';

// Load all the Slim stuff
// Middleware
require 'middleware/middleware.php';

// Routes
require 'routes/session.php';
require 'routes/me.php';
require 'routes/public.php';

// DB
require 'db/db.php';

// Add session middleware
$app->add(new \Slim\Middleware\SessionCookie(
	array(
		'secret' => 'thisismysecret',
		'expires' => '7 days',
	))
);
// Add camelcase middleware
$app->add(new \CamelCaseMiddleware());

$app->run();