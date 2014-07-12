<?php

function to_camel_case($str, $capitalise_first_char = false) {
    if($capitalise_first_char) {
		$str[0] = strtoupper($str[0]);
    }
    $func = create_function('$c', 'return strtoupper($c[1]);');
    return preg_replace_callback('/_([a-z])/', $func, $str);
}

function array_to_camel_case($array) {
	$returnArray = array();

	foreach($array as $item) {
		if(is_array($item)) {
			$returnArray[] = array_to_camel_case($item);
		} else if (is_object($item)) {
			$returnArray[] = object_to_camel_case($item);
		} else {
			// Its a value
			$returnArray[] = $item;
		}
	}

	return $returnArray;

}
function object_to_camel_case($object) {
	$returnObject = new stdClass();
	foreach($object as $key => $item) {
		if(is_array($item)) {
			$returnObject->{to_camel_case($key)} = array_to_camel_case($item);
		} else if (is_object($item)) {
			$returnObject->{to_camel_case($key)} = object_to_camel_case($item);
		} else {
			// Its a value
			$returnObject->{to_camel_case($key)} = $item;
		}
	}
	return $returnObject;
}
class CamelCaseMiddleware extends \Slim\Middleware
{
    public function call()
    {
        // Get reference to application
        $app = $this->app;

        // Run inner middleware and application
        $this->next->call();

        // Converts any snake case to camel case
        $res = $app->response;
        $body = $res->getBody();
        $data = json_decode($body);

        if($data != NULL) {
			if(is_array($data)) {
	        	$res->setBody(json_encode(array_to_camel_case($data)));
	        } else {
	    		$res->setBody(json_encode(object_to_camel_case($data)));
	        }
        }
    }
}