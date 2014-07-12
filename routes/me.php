<?php
date_default_timezone_set("Australia/Brisbane");

$_SESSION['userId'] = 1;

// Restricted to logged in current user
$app->group('/me', $authenticate($app), function () use ($app) {
// $app->group('/me', function () use ($app) {
	$app->get('/hello', function() use ($app) {
		// print_r($_SESSION);
		// die();
		echo '{"hello": "world"}';
		// $projectId = 1;
		 // else {
	    	// echo 'already done';
	    // }

	});

	$app->get('/user', function() {
		$user = R::load('user', $_SESSION['userId']);	
		echo json_encode($user->export(), JSON_NUMERIC_CHECK);
	});

	$app->get('/partner', function() {
		$user = R::load('user', $_SESSION['userId']);
		$partner = R::load('user', $user->partner_id);
		echo json_encode($partner->export(), JSON_NUMERIC_CHECK);
	});

	$app->post('/partner', function() use ($app) {
		$user = R::load('user', $_SESSION['userId']);	
		$partnerData = json_decode($app->request->getBody());
		$partnerCode = $partnerData->code;
		$partner = R::findOne('user', ' code = :code ', array(
			'code' => $partnerCode)
		);
		$user->partner_id = $partner->id;
		R::store($user);
		echo json_encode($partner->export(), JSON_NUMERIC_CHECK);
	});
	// $app->post('/partner'. function() {
		// $user = R::load('user', $_SESSION['userId']);	

		// $partnerData = json_decode($app->request->getBody());
		// $partnerCode = $partnerData->code;
		// $partner = R::findOne('user', ' code = :code ', array(
		// 	':code' => $partnerCode));
		// $user->partner = $partner;
		// R::store($user);
	// });

	$app->get('/events', function() {
		$user = R::load('user', $_SESSION['userId']);
		$partner = R::load('user', $user->partner_id);

		$events = R::findAll('event', ' ORDER BY start ASC LIMIT 10 ');
		foreach($events as &$event) {
			// Get the hearts for the couple
			$event->hearts = R::find('heart', ' event_id = :event_id AND (user_id = :user_id OR user_id = :partner_id) ', array(
				'event_id' => $event->id,
				'user_id' => $user->id,
				'partner_id' => $partner->id
			));
		}
		echo json_encode(R::exportAll($events));
	});

	$app->get('/events2', function() use ($app) {
		$user = R::load('user', $_SESSION['userId']);
		$partner = R::load('user', $user->partner_id);

		// Get all the events that have hearts
		$hearts = R::find('heart', ' user_id = :user_id OR user_id = :partner_id ', array(
			'user_id' => $user->id,
			'partner_id' => $partner->id
		));

		$eventIds = array();
		foreach($hearts as $heart) {
			$eventIds[] = $heart->id;
		}
		$events = R::loadAll('event', array_unique($eventIds));
		foreach($events as &$event) {
			// Get the hearts for the couple
			$event->hearts = $event->ownHeartList;
		}
		echo json_encode(R::exportAll($events));
	});

	// $app->get('/hearts', function() {
	// 	$user = R::load('user', $_SESSION['userId']);
	// 	$partner = R::load('user', $user->partner_id);;
	// 	$hearts = R::find('hearts', ' ')
	// });

	$app->post('/hearts', function() use ($app) {
		$user = R::load('user', $_SESSION['userId']);;

		// Find existing heart with partner
		$heart = R::dispense('heart');
		$heartData = json_decode($app->request->getBody());
	    $heart->import($heartData);
	    $heart->user = $user;
	    R::store($heart);
	    echo json_encode($heart->export(), JSON_NUMERIC_CHECK);
	});

	

	$app->get('/setup', function() use ($app) {
		R::nuke();

		// $_SESSION['userId'] = 1;
		$user = R::dispense('user');
		$user->name = 'Craig McNamara';
		$user->email = 'cmcnamara87@gmail.com';
		R::store($user);

		$project = R::dispense('project');
		$project->name = 'Progress';
		$project->user = $user;
		R::store($project);

		$directory = R::dispense('directory');
		$directory->path = '/Users/cmcnamara87/Sites/progress';
		$directory->project = $project;
		R::store($directory);

		echo json_encode($project->export(), JSON_NUMERIC_CHECK);
	});
	$app->get('/user', function() {
		$user = R::load('user', $_SESSION['userId']);	
		echo json_encode($user->export(), JSON_NUMERIC_CHECK);
	});
	$app->get('/projects', function() {
		$user = R::load('user', $_SESSION['userId']);
		$projects = $user->ownProjectList;
		foreach($projects as $project) {
			$previousTime = null;
			$project->seconds = 0;
			foreach($project->ownProgressList as $progress) {
				$hasAlreadyMadeProgress = isset($previousTime);
				if($previousTime) {
					$hasWorkedWithinAnHour = $previousTime > ($progress->created - PROGRESS_ACTIVE_TIME_MINUTES * 60);
					if($hasWorkedWithinAnHour) {
						$project->seconds += min($progress->created - $previousTime, PROGRESS_MAX_AMOUNT_MINUTES * 60);	
					} else {
						$project->seconds += PROGRESS_DEFAULT_AMOUNT_MINUTES;
					}
				}
				$previousTime = $progress->created;
			}
			$project->time = gmdate("H:i:s", $project->seconds);
		}
		$export = array_map(function($project) {
			$result = new stdClass();
			$result->id = $project->id;
			$result->name = $project->name;
			$result->seconds = $project->seconds;
			$result->time = $project->time;
			$result->directories = R::exportAll($project->ownDirectoryList);
			return $result;
		}, $projects);

		echo json_encode(array_values($export), JSON_NUMERIC_CHECK);
	});
	$app->post('/projects', function() use ($app) {
		$project = R::dispense('project');
	    $project->import($app->request->post());
	    $project->user = R::load('user', $_SESSION['userId']);
	    R::store($project);
	    echo json_encode($project->export(), JSON_NUMERIC_CHECK);
	});
	$app->post('/projects/:projectId/directories', function($projectId) use ($app) {
		// print_r($app->request-/>post());
		$directory = R::dispense('directory');
	    $directory->import($app->request->post());
	    $directory->project = R::load('project', $projectId);
	    R::store($directory);
	});
	$app->get('/projects/:projectId/screenshots', function ($projectId) {
		echo 'got screenshots!';
	});
	$app->post('/projects/:projectId/screenshots', function ($projectId) use ($app) {
	    if (!isset($_FILES['file'])) {
	        echo "No file uploaded!!";
	        return;
	    }

	    $user = R::load('user', $_SESSION['userId']);
	    $project = R::load('project', $projectId);

	    $uploaddir = "/var/www/html/progress/uploads/";
		$uploadfile = $uploaddir . basename($_FILES['file']['name']);

		// echo '<pre>';
		if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
			$app->halt(400, 'Possible file upload attack');
		}

		// File upload success
		$collection = R::dispense('collection');
		$collection->user = $user;
		$collection->project = $project;
		R::store($collection);

		$file = R::dispense('file');
		$file->name = basename($_FILES['file']['name']);
		$file->user = $user;
		$file->project = $project;
		$file->collection = $collection;
		R::store($file);

		$collection = R::load('collection', $collection->id);

		$post = R::dispense('post');
   		$post->user = $user;
   		$post->project = $project;
   		$post->type = 'SCREENSHOT_COLLECTION';
   		$post->collection = $collection;
   		$post->created = time();
   		$post->modified = time();
   		$post->text = $app->request->post('text');
   		R::store($post);

		echo json_encode($collection->export(false, false, true), JSON_NUMERIC_CHECK);
	});
	$app->post('/projects/:projectId/progress', function($projectId) use ($app) {

		// Debug logging
	    $project = R::load('project', $projectId);
		$user = R::load('user', $_SESSION['userId']);	
		$app->log->debug(date('l jS \of F Y h:i:s A') . " - Progress for User ID: " . $_SESSION['userId'] . " {$user->name}, Project: $projectId {$project->name}");

		// Code for tuition integration
	    $tuition = R::findOne('tuition', ' project_id = :project_id ORDER BY created DESC ', array(':project_id' => $projectId));

	    if($tuition) {
	    	$too_old = $tuition->created < (time() - (60 * TUITION_PING_TIME_MINUTES));
	    }
	    if(!$tuition || $too_old) {
			// Send progress starting
	    	// http://tuition.jonnylu.com/api_php/?t_p=[1,0]&t_m=[EMAIL_HERE]
	    	// Set the POST data
	    	$user = R::load('user', $_SESSION['userId']);
			$postdata = http_build_query(array());
		 
			// Set the POST options
			$opts = array('http' => 
				array (
					'method' => 'POST',
					'header' => 'Content-type: application/xwww-form-urlencoded',
					'content' => $postdata
				)
			);
		 
			// Create the POST context
			$context  = stream_context_create($opts);
		 
			// POST the data to an api
			$url = "http://tuition.jonnylu.com/api_php/?t_p=1&t_m={$user->email}&t_f=desktopApp";
			$result = file_get_contents($url, false, $context);

			$tuition = R::dispense('tuition');
			$tuition->created = time();
			$tuition->project = $project;
			R::store($tuition);
	    }
	   
	   	$lastProgress = R::findOne('progress', ' project_id = :project_id ORDER BY created DESC ', array(':project_id' => $projectId));
	   	$firstPost = R::findOne('post', ' project_id = :project_id AND user_id = :user_id ', array(':project_id' => $projectId, ':user_id' => $user->id));

	   	$hasNoPostForProject = !$firstPost; // can remove this if people stop working
	   	$hasRecentlyStartedWorking = $lastProgress && $lastProgress->created + 60 * 60 < time();
	   	if($hasNoPostForProject || $hasRecentlyStartedWorking) {
	   		$app->log->debug(date('l jS \of F Y h:i:s A') . " - Making Post for User ID: " . $_SESSION['userId'] . " {$user->name}, Project: $projectId {$project->name}");

	   		$post = R::dispense('post');
	   		$post->user = $user;
	   		$post->project = $project;
	   		$post->type = 'STARTED_WORKING';
	   		$post->created = time();
	   		$post->modified = time();
	   		R::store($post);
	   	}

		$progress = R::dispense('progress');
		$progress->created = time();
		$progress->modified = time();
	    $progress->import($app->request->post());
	    $progress->project = $project;
	    $progress->user = $user;
	    R::store($progress);
	});

	$app->get('/profile', function() {
		$user = R::load('user', $_SESSION['userId']);
		echo json_encode($user->export());
	});

	$app->post('/locations', function() use ($app) {
		// Get the post data
		$locationData = json_decode($app->request->getBody());

	    //Create location
	    $location = R::dispense('location');

	    $location->import($locationData);
	    $user = R::load('user', $_SESSION['userId']);
		$location->user = $user;
		$location->created = time();
	    R::store($location);

	    echo json_encode($location->export(), JSON_NUMERIC_CHECK);
	});

	/**
	 * Stores a users device id
	 */
	$app->post('/device', function() use ($app) {
		$deviceData = json_decode($app->request->getBody());
		$user = R::load('user', $_SESSION['userId']);
		$user->deviceId = $deviceData->id;
		R::store($user);
		echo json_encode($deviceData, JSON_NUMERIC_CHECK);
		// echo json_encode($user->export());
	});

	$app->get('/users', function() use ($app) {
		$name = $app->request->get('name');
		if(!$name) {
			// Bad request, name needed
			$app->halt(400, 'GET parameter "name" must be specified');	
		}

		$dbUsers = R::find( 'user', ' id != :user_id AND name LIKE :name', array(
			':user_id' => $_SESSION['userId'],
			':name' => '%' . $name . '%'
		));

		$users = array();
		foreach($dbUsers as $dbUser) {
			$user = new stdClass();

			$contact = R::findOne('contact', ' (from_user_id = :userId AND to_user_id = :currentUserId) OR (from_user_id = :currentUserId AND to_user_id = :userId) ', 
				array(
					':userId' => $dbUser->id,
					':currentUserId' => $_SESSION['userId'],
				)
			);
			if($contact && $contact->id != 0) {
				if ($contact->status != 0) {
					$user->status = 'accepted';
				} else if ($contact->fromUserId == $_SESSION['userId']) {
					$user->status = 'sent';
				} else {
					$user->status = 'requested';
				}
			} 
			foreach($dbUser as $key => $value) {
				if($key != 'password' && $key != 'device_id' && $key != 'email') {
					$user->{$key} = $value;	
				}
				if($key == 'email') {
					$user->image = "http://www.gravatar.com/avatar/" . md5(strtolower(trim($value)));		
				}
			}
			$users[] = $user;
		}

		echo json_encode($users,  JSON_NUMERIC_CHECK);
	});

	$app->post('/users/:userId/request', function($userId) use ($app) {
		$contact = R::dispense('contact');
		$contact->fromUserId = $_SESSION['userId'];
		$contact->toUserId = $userId;
		$contact->status = 0;
		R::store($contact);
	});

	/**
	 * Gets all contacts
	 */
	$app->get('/contacts', function() use ($app) {
		$contacts = getContacts();

		$type = $app->request->get('type');
		if($type && $type == 'requested') {
			$contacts = array_values(array_filter($contacts, function ($contact) {
				return $contact->status == 'requested';
			}));
		} else if($type && $type == 'accepted') {
			$contacts = array_values(array_filter($contacts, function ($contact) {
				return $contact->status == 'accepted';
			}));
		}

		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

		if($meLocation && $meLocation->id != 0) {
			foreach($contacts as $contact) {
				// See if we have any pings from them that are recent
				$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
					array(
						':from_contact_id' => $contact->id,
						':to_contact_id' => $_SESSION['userId'],
						'time' => time() - 60 * PING_TIMEOUT_MINUTES
					)
				);
				if($ping && $ping->id != 0) {
					$location = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
					if($location && $location->id !== 0) {
						$distance = haversineGreatCircleDistance($ping->latitude, $ping->longitude, $location->latitude, $location->longitude);	

						// Calculate the distance
						if($distance < PING_NEARBY_DISTANCE_METERS) {
							$exportedPing = $ping->export();
							$exportedPing['contactId'] = $contact->id;
							$contact->ping = $exportedPing;
						}	
					}
				}
			}	
		}
		echo json_encode($contacts,  JSON_NUMERIC_CHECK);
	});

	$app->get('/contacts/etas', function() use ($app) {
		$contacts = R::find( 'user', ' id != :user_id ', array(':user_id' => $_SESSION['userId']));

		$etas = calculateEtas($contacts);
		// $etas = array();
		// foreach($contacts as $contact) {
		// 	$eta = calculateEta($contact->id);
		// 	$etas[] = $eta;
		// }
		echo json_encode($etas, JSON_NUMERIC_CHECK);
	});
	$app->get('/contacts/pings', function() {
		$contacts = R::find( 'user', ' id != :user_id ', array(':user_id' => $_SESSION['userId']));
		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

		$pings = array();
		
		foreach($contacts as $contact) {
			// echo '<br/>looking at contact ' . $contact->id;
			$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
				array(
					':from_contact_id' => $contact->id,
					':to_contact_id' => $_SESSION['userId'],
					'time' => time() - 60 * PING_TIMEOUT_MINUTES
				)
			);
			if($ping && $ping->id != 0) {
				// echo '<br/>found a ping ' . $ping->id;
				$location = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
				if($location && $location->id !== 0) {
					$distance = haversineGreatCircleDistance($ping->latitude, $ping->longitude, $location->latitude, $location->longitude);	
					// echo '<br/>distance is ' . $distance;
					// Calculate the distance
					if($distance < PING_NEARBY_DISTANCE_METERS) {
						$exportedPing = $ping->export();
						$exportedPing['contactId'] = $contact->id;
						$pings[] = $exportedPing;
					}	
				}
			}
		}
		
		echo json_encode($pings, JSON_NUMERIC_CHECK);			
	});

	$app->get('/contacts/:contactId', function($contactId) use ($app) {
		$user = R::load('user', $contactId);
		echo json_encode($user->export());
	});

	$app->get('/contacts/:contactId/eta', function($contactId) use ($app) {
		$contact = R::load('user', $contactId);

		$etas = calculateEtas(array($contact));

		// Send push notification if we know everyones device id
		// and its not an update request
		// if($contact->deviceId) {
		// 	pwCall('createMessage', 
		// 		array(
		// 	    	'application' => PW_APPLICATION,
		// 	    	'auth' => PW_AUTH,
		// 	    	'notifications' => array(
		// 		        array(
		// 		                'send_date' => 'now',
		// 		                'content' => $me->name . ' checked your ETA.',
		// 		                'devices' => array(
	 //              					 $contact->deviceId
	 //            				),
		// 		        )
		// 		    )
		// 	    )
		// 	);
		// } 
		echo json_encode($etas[0], JSON_NUMERIC_CHECK);
	});


	$app->get('/contacts/:contactId/ping', function($contactId) {
		$contact = R::load('contact', $contactId);

		$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
			array(
				':from_contact_id' => $contact->id,
				':to_contact_id' => $_SESSION['userId'],
				'time' => time() - 60 * PING_TIMEOUT_MINUTES
			)
		);
		if($ping && $ping->id != 0) {
			$distance = haversineGreatCircleDistance($ping->latitude, $ping->longitude, $meLocation->latitude, $meLocation->longitude);	
			// Calculate the distance
			if($distance < PING_NEARBY_DISTANCE_METERS) {
				echo json_encode($ping->export(), JSON_NUMERIC_CHECK);
				return;
			}
		}
		$app->halt(404);
	});
	$app->post('/contacts/:contactId/ping', function($contactId) use ($app) {
		$contact = R::load('user', $contactId);

		$me = R::load('user', $_SESSION['userId']);
		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));
		$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$meLocation->latitude},{$meLocation->longitude}&sensor=false";
		$addressLookup = json_decode(file_get_contents($url));
		$address = $addressLookup->results[0]->formatted_address;

		if($contact->deviceId) {
			// Check the existing pins
			$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
				array(
					':from_contact_id' => $_SESSION['userId'],
					':to_contact_id' => $contact->id,
					'time' => time() - 60 * PING_PUSH_TIMEOUT_MINUTES
				)
			);
			if(!$ping || $ping->id == 0) {
				pwCall('createMessage', 
					array(
				    	'application' => PW_APPLICATION,
				    	'auth' => PW_AUTH,
				    	'notifications' => array(
					        array(
					                'send_date' => 'now',
					                'content' => $me->name . ' pinged you from ' . $address,
					                'devices' => array(
		              					 $contact->deviceId
		            				),
					        )
					    )
				    )
				);
			}
		}

		$ping = R::dispense('ping');
		$ping->fromContactId = $me->id;
		$ping->toContactId = $contactId;
		$ping->address = $address;
		$ping->latitude = $meLocation->latitude;
		$ping->longitude = $meLocation->longitude;
		$ping->created = time();
		R::store($ping);
	});
	$app->post('/contacts/:contactId/accept', function($contactId) use ($app) {
		$contact = R::findOne('contact', ' from_user_id = :contactId AND to_user_id = :currentUserId ', 
			array(
				':contactId' => $contactId,
				':currentUserId' => $_SESSION['userId'],
			)
		);
		$contact->status = 1;
		R::store($contact);
	});
	$app->post('/contacts/:contactId/reject', function($contactId) use ($app) {
		$contact = R::findOne('contact', ' from_user_id = :contactId AND to_user_id = :currentUserId ', 
			array(
				':contactId' => $contactId,
				':currentUserId' => $_SESSION['userId'],
			)
		);
		R::trash($contact);
	});
	$app->post('/locations', function() {
		// {latitude: 1, longitude: 1}
		$location = R::dispense('location');
		
	});
});

/**
 * Works out the status of a contact
 * 
 * @param  [type] $contact [description]
 * @return [type]          
 */
function calculateStatus($contact) {
	// online, offline, movement
}

function getPing($contact) {

}

function getContacts() {
	$dbContacts = R::find( 'contact', ' to_user_id = :user_id OR from_user_id = :user_id', array(':user_id' => $_SESSION['userId']));

	$contacts = array();
	foreach($dbContacts as $dbContact) {
		$contact = new stdClass();

		if($dbContact->toUserId !== $_SESSION['userId']) {
			$user = R::load('user', $dbContact->toUserId);
		} else {
			$user = R::load('user', $dbContact->fromUserId);
		}

		if ($dbContact->status != 0) {
			$contact->status = 'accepted';
		} else if ($dbContact->fromUserId == $_SESSION['userId']) {
			$contact->status = 'sent';
		 // = $dbContact->status;
		} else {
			$contact->status = 'requested';
		}

		foreach($user as $key => $value) {
			if($key != 'password' && $key != 'device_id') {
				$contact->{$key} = $value;	
			}
		}
		$contact->image = "http://www.gravatar.com/avatar/" . md5(strtolower(trim($contact->email)));
		$contacts[] = $contact;
	}
	return $contacts;
}

function calculateEtas($contacts) {
	$origins = array();
	$contactsWithLocations = array();
	$etas = array();
	
	foreach($contacts as $contact) {
		$location = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
		
		// All the contacts with a location < 1 day old
		if($location && $location->id !== 0 && time() - (24 * 60 * 60) < $location->created) {
			$contact->location = $location;
			$contactsWithLocations[] = $contact;

			$origins[] = "{$location->latitude},{$location->longitude}";
		} else {
			// Contact is online
			$eta = new stdClass();
			// @todo: add in actual location
			$eta->status = 'offline';
			$eta->lastSeen = $location->created;
			$eta->contactId = $contact->id;
			// Make the ETA object
			$etas[] = $eta;
		}
	}

	// Work out the etas
	$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));
	$departureTime = time() + 60;
	$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins=" . implode("|", $origins) . "&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false&departure_time=$departureTime";
	$distanceMatrix = json_decode(file_get_contents($url));

	// Go throguh each row, and pull out the time
	
	foreach($distanceMatrix->rows as $index => $row) {
		$duration = $row->elements[0]->duration->value;
		$contact = $contactsWithLocations[$index];
		

		$eta = new stdClass();
		// @todo: add in actual location
		$eta->status = 'online';
		$eta->duration = $duration;
		$eta->lastSeen = $contact->location->created;
		$eta->now = time();
		$eta->movement = calculateMovement($contact);
		$eta->contactId = $contact->id;
		// Make the ETA object
		$etas[] = $eta;
	}

	return $etas;
}

function calculateMovement($contact) {
	// Get the users location
	$contactId = $contact->id;
	$contactLocations = R::find('location', ' user_id = :user_id ORDER BY created DESC LIMIT 2 ', array(':user_id' => $contactId));

	if(count($contactLocations) == 0) {
		return false;		
	}

	$contactLocations = array_values($contactLocations);
	$contactLocation = $contactLocations[0];

	$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

	// Get the contacts movement
	// check if stationary
	if($contactLocation->created < time() - (60 * LOCATION_TIMEOUT_MINUTES) || count($contactLocations) == 1) {
		// the user has been stationary for 10 mins
		// mark them as stationary
		$contactMovement = 'stationary';
	} else {
		// they are moving, are they moving towards us
		$newLocation = $contactLocations[0];
		$oldLocation = $contactLocations[1];
		$newLocationDistance = haversineGreatCircleDistance($newLocation->latitude, $newLocation->longitude, $meLocation->latitude, $meLocation->longitude);
		$oldLocationDistance = haversineGreatCircleDistance($oldLocation->latitude, $oldLocation->longitude, $meLocation->latitude, $meLocation->longitude);

		// if(abs($newLocationDistance - $oldLocationDistance) < 30) {
		// 	// they havent moved very far, all it stationary
		// 	$contactMovement = 'stationary';
		// } else {
			if($newLocationDistance < $oldLocationDistance) {
				// moving closer
				$contactMovement = 'towards';
			} else {
				$contactMovement = 'away';
			}	
		// }
		
	}
	return $contactMovement;
}

// function calculateEta($contactId) {
	


// 	// gold coast
// 	// $contactLocation = new stdClass();
// 	// $contactLocation->latitude = -28.0167;
// 	// $contactLocation->longitude = 153.4000;

// 	// queen st mall
// 	// $meLocation = new stdClass();
// 	// $meLocation->latitude = -27.4673045983608;
// 	// $meLocation->longitude = 153.0282677023206;

// 	$departureTime = time() + 5;
// 	$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins={$contactLocation->latitude},{$contactLocation->longitude}&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false&departure_time=$departureTime";
// 	$distanceMatrix = json_decode(file_get_contents($url));

// 	$timeSeconds = $distanceMatrix->rows[0]->elements[0]->duration->value;
	
// 	$eta = new stdClass();
// 	// @todo: add in actual location
// 	$eta->suburb = "St Lucia";
// 	$eta->time = $timeSeconds;
// 	$eta->lastSeenAt = $contactLocation->created;
// 	$eta->serverTime = time();
// 	$eta->movement = $contactMovement;
// 	$eta->contactId = $contactId;

// 	return $eta;
// }
/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}