<?php 

define('PW_AUTH', 'DTtBKUmiECRPdcd0tGFGj7SgVFijHKxlCAtSmH9qlJqHznHlxQtRC06mFZOQlz9dBiIrv5jMLWVq4ARfa53M');
define('PW_APPLICATION', 'C9585-0582F');
define('PW_DEBUG', false);
 
function pwCall($method, $data) {
    $url = 'https://cp.pushwoosh.com/json/1.3/' . $method;
    $request = json_encode(array('request' => $data));
 
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
 
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
 
    if (defined('PW_DEBUG') && PW_DEBUG) {
        print "[PW] request: $request\n";
        print "[PW] response: $response\n";
        print "[PW] info: " . print_r($info, true);
    }
}