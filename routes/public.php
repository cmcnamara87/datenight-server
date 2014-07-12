<?php

$app->get('/ingest', function() use ($app) {
	R::nuke();

	$xml = simplexml_load_file('http://www.trumba.com/calendars/brisbane-events-rss.rss?filterview=parks');
	$namespaces = $xml->getNamespaces(true);

	foreach($xml->channel->item as $item) {
		$xCal = $item->children($namespaces['xCal']);
		$xTrumba = $item->children($namespaces['x-trumba']);

		$event = R::dispense('event');
		$event->title = (string)$item->title;
		$event->source = 'parks';
		$event->location = (string)$xCal->location;
		$event->description = (string)$xCal->description;
		$event->start = strtotime($xCal->dtstart);
		$event->end = strtotime($xCal->dtend);
		$event->uid = (string)$xCal->uid;
		$customFields = $xTrumba->customfield;
		foreach($customFields as $customField) {
			$name = strtolower(str_replace(' ', '', trim($customField->attributes()->name)));
			$event->{$name} = (string)$customField;
		}
		R::store($event);
	}
});

$app->get('/events', function() {
	$events = R::findAll('event', ' LIMIT 10 ');
	echo json_encode(R::exportAll($events));
});
$app->get('/setup', function() {
	R::wipe('couple');
	R::wipe('user');
	
	$userA = R::dispense('user');
	$userA->email = 'cmcnamara87@gmail.com';
	$userA->name = 'Craig McNamara';
	$userA->password = md5('test');
	$userA->code = 'pussycat36';
	R::store($userA);

	$userB = R::dispense('user');
	$userB->email = 'nick@gmail.com';
	$userB->name = 'Nick Georgiou';
	$userB->password = md5('test');
	$userB->code = 'foxylady18';
	R::store($userB);

	R::store($userA);
});

$app->get('/me/following/posts', function() {
	$posts = R::findAll('post', ' ORDER BY created DESC ');
	foreach($posts as &$post) {
		$post->user = R::load('user', $post->user_id);
		$post->project = R::load('project', $post->project_id);
		$post->collection = R::load('collection', $post->collection_id);
		$post->collection->ownFileList;
	}
	echo json_encode(R::exportAll($posts));
});
$app->get('/me/following', function() {
	$users = R::findAll('user');
	echo json_encode(R::exportAll($users));
});

$app->get('/me/following/online', function() {
	$users = R::findAll('user');
	$online = array();
	foreach($users as $user) {
		// Get the last project
		$lastProgress = R::findOne('progress', ' user_id = :user_id ORDER BY created DESC ', array(':user_id' => $user->id));	

		if($lastProgress && $lastProgress->created + 60*60 > time()) {	

			$user->activeProject = R::load('project', $lastProgress->project_id)->export();
			$user->state = 'idle';
			$user->lastProgress = $lastProgress->export(false, false, true);
			if($lastProgress->created + 15*60 > time()) {			
				$user->state = 'active';
			}
			$online[] = $user->export(false, false, true);;
		}
	}
	echo json_encode($online);
});

$app->get('/users/:userId/posts', function($userId) {
	$user = R::load('user', $userId);
	$posts = R::find('post', ' user_id = :user_id ORDER BY created DESC ', array('user_id' => $userId));
	echo json_encode(R::exportAll($posts));
});

$app->get('/users/:userId/projects', function($userId) {
	$user = R::load('user', $userId);
	$projects = $user->ownProjectList;
	header( 'Content-Type: text/html' );
	foreach($projects as $project) {
		// $time = 0;
		$previousTime = null;
		$project->seconds = 0;
		foreach($project->ownProgressList as $progress) {
			$hasAlreadyMadeProgress = !!$previousTime;
			if($hasAlreadyMadeProgress) {
				$hasWorkedWithinAnHour = $previousTime > ($progress->created - PROGRESS_ACTIVE_TIME_MINUTES * 60);
				if($hasWorkedWithinAnHour) {
					$project->seconds += min($progress->created - $previousTime, PROGRESS_MAX_AMOUNT_MINUTES * 60);	
				} else {
					$project->seconds += PROGRESS_DEFAULT_AMOUNT_MINUTES;
				}
			}
			$previousTime = $progress->created;
		}
		$project->time = gmdate("z\d G\h i\m s\s", $project->seconds);
	}
	

	$export = array_map(function($project) {
		$result = new stdClass();
		$result->id = $project->id;
		$result->name = $project->name;
		$result->time = $project->time;
		$result->seconds = $project->seconds;
		$result->directories = R::exportAll($project->ownDirectoryList);
		$result->user = R::load('user', $project->user_id)->export();
		return $result;
	}, $projects);

	echo json_encode(array_values($export), JSON_NUMERIC_CHECK);
});

$app->get('/users/:userId/projects/:projectId', function($userId, $projectId) {
	$project = R::load('project', $projectId);
	$project->user = R::load('user', $userId);

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
	$project->time = gmdate("z\d G\h i\m s\s", $project->seconds);
	echo json_encode($project->export(false, false, true));
});

$app->get('/users/:userId/projects/:projectId/posts', function($userId, $projectId) {
	$user = R::load('user', $userId);
	$posts = R::find('post', ' user_id = :user_id AND project_id = :project_id ORDER BY created DESC ', array('user_id' => $userId, 'project_id' => $projectId));
	// $posts = R::exportAll($posts);
	foreach($posts as &$post) {
		$post->user = R::load('user', $post->user_id);
		$post->project = R::load('project', $post->project_id);
		$post->collection = R::load('collection', $post->collection_id);
		$post->collection->ownFileList;
	}
	echo json_encode(R::exportAll($posts));
});
