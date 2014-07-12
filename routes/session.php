<?php

$app->get('/hello2', function() use ($app) {
    echo '{"test_thing": "go now"}';
});

$authenticate = function ($app) {
    return function () use ($app) {

    	// Check there is a user id and email set
        if (isset($_SESSION['userId'])) {
        	$user = R::load('user', $_SESSION['userId']);

            if($user->id == 0) {
                $app->halt(401, 'Login Required.');
            }
        } else {
            $app->halt(401, 'Login Required.');
        }
    };
};

$app->group('/users', function () use ($app) {

    /**
     * Creates a new user
     */
    $app->post('/register', function() use ($app) {
        // Make a new user
        $user = R::dispense('user');
        $user->code = 'pussycat' . rand(1,100);;
        R::store($user);

        $_SESSION['userId'] = $user->id;

        echo json_encode($user->export(), JSON_NUMERIC_CHECK);
    });
});