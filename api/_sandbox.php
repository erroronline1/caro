<?php

// utility method?
function get_content($URL, $proxy = null, $auth = null){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $URL);
    if ($proxy) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    if ($auth) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
    $data = curl_exec($ch);
    curl_close($ch);
    //var_dump(curl_getinfo($ch));
    return $data;
}

// config settings for proxy and auth?
var_dump( get_content('http://example.com', '127.0.0.1:8888', 'user:password'));

//phpinfo();
?>