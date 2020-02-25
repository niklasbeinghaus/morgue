<?php

class CurlClient
{
    function get(
        $url,
        array $params = null,
        $user_pass = null,
        $proxy = null,
        $timeout = 10
    ) {
        $query_string = empty($params)
            ? ''
            : '?' . http_build_query($params);
        $ch = curl_init($url . $query_string);
        $options = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
        );
        if ($user_pass) {
            if ($user_pass != ":") {
                $options[CURLOPT_USERPWD] = $user_pass;
            }
        }
        if ($proxy) {
            $options[CURLOPT_PROXY] = $proxy;
        }
        curl_setopt_array($ch, $options);
        $result = trim(curl_exec($ch));
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status_code != 200) {
            error_log("Got unexpected HTTP status code $status_code from $url");
        }
        curl_close($ch);
        return $result;
    }
}
