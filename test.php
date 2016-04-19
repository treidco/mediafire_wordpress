<?php

class Security
{

    private $email = "";
    private $pwd = "";
    private $app_id = "";
    private $key = "";

    private $base_uri = "https://www.mediafire.com";

    public function execute()
    {
        $token_resp = $this->get_session_token();

        $key = $token_resp->{"secret_key"};
        $time = $token_resp->{"time"};
        $token = $token_resp->{"session_token"};

        $api_resp = $this->first_api_call($token, $time, $key);
        $new_key = $this->generate_new_key($key);
        $api_resp2 = $this->first_api_call($token, $time, $new_key);

        return $api_resp;
    }

    private function get_session_token()
    {
        $session_token_url = "/api/1.1/user/get_session_token.php?email=" . $this->email
            . "&password=" . $this->pwd
            . "&application_id=" . $this->app_id
            . "&signature=" . $this->compute_session_token_signature()
            . "&token_version=2";

        $request_url = $this->base_uri . $session_token_url;
        $resp = file_get_contents($request_url);
        $xml = simplexml_load_string($resp);

        return $xml;
    }

    private function compute_session_token_signature()
    {
        return sha1($this->email . $this->pwd . $this->app_id . $this->key);
    }

    private function first_api_call($session_token, $time, $key)
    {
        $uri = "/api/1.5/user/get_info.php?session_token=" . $session_token;
        $api_sig = $this->compute_api_signature($key, $time, $uri);

        $url = "https://www.mediafire.com" . $uri . "&signature=" . $api_sig;

        $response = file_get_contents($url);
        $xml_resp = simplexml_load_string($response);

        return $xml_resp;
    }


    private function compute_api_signature($secret_key, $time, $uri)
    {
        $mod_key = $secret_key % 256;
        return md5($mod_key . $time . $uri);
    }

    private function generate_new_key($key)
    {
        $magic_number = "16807";
        $magic_modulo = "2147483647";

        return ($key * $magic_number) % $magic_modulo;

    }
}


$security = new Security();
$security->execute();