<?php

class Security
{

    private $config;
    private $current_key;
    private $refresh_key = false;
    private $base_uri = "https://www.mediafire.com";

    function __construct()
    {
        $this->config = $this->read_config();
    }

    private function read_config()
    {
        $conf_file = fopen("conf.txt", "r") or die("Unable to open file!");
        $content = fread($conf_file, filesize("conf.txt"));
        fclose($conf_file);

        $conf = array();

        $content2 = explode("\n", $content);
        foreach ($content2 as $line)
        {
            $line_array = explode("=", $line);
            $conf[$line_array[0]] = $line_array[1];
        }
        return array_filter($conf);
    }

    public function execute()
    {
        $token_resp = $this->get_session_token();

        $this->current_key = $token_resp->{"secret_key"};
        $time = $token_resp->{"time"};
        $token = $token_resp->{"session_token"};
        $path = "/api/1.5/user/get_info.php?session_token=";

        $api_resp = $this->call_api($token, $time, $path);
//        $new_key = $this->generate_new_key($key);
        $api_resp2 = $this->call_api($token, $time, $path);

        print_r($api_resp2);

        return $api_resp;
    }

    private function get_session_token()
    {
        $session_token_url = "/api/1.1/user/get_session_token.php?email=" . $this->config["email"]
            . "&password=" . $this->config["password"]
            . "&application_id=" . $this->config["app_id"]
            . "&signature=" . $this->compute_session_token_signature()
            . "&token_version=2";

        $request_url = $this->base_uri . $session_token_url;
        $resp = file_get_contents($request_url);
        $xml = simplexml_load_string($resp);

        return $xml;
    }

    private function compute_session_token_signature()
    {
        return sha1($this->config["email"] . $this->config["password"] . $this->config["app_id"] . $this->config["app_key"]);
    }

    private function call_api($session_token, $time, $path)
    {

        if ($this->refresh_key)
        {
            $this->current_key = $this->generate_new_key($this->current_key);
            $this->refresh_key = false;
        }

        $uri = $path . $session_token;
        $api_sig = $this->compute_api_signature($this->current_key, $time, $uri);

        $url = $this->base_uri . $uri . "&signature=" . $api_sig;

        $response = file_get_contents($url);
        $xml_resp = simplexml_load_string($response);

        if ($xml_resp->{"new_key"} == "yes")
        {
            $this->refresh_key = true;
        }

        return $xml_resp;
    }

    private function generate_new_key($key)
    {
        $magic_number = "16807";
        $magic_modulo = "2147483647";

        return ($key * $magic_number) % $magic_modulo;

    }

    private function compute_api_signature($secret_key, $time, $uri)
    {
        $mod_key = $secret_key % 256;
        return md5($mod_key . $time . $uri);
    }

    private function get_user_info()
    {

    }
}


$security = new Security();
$security->execute();