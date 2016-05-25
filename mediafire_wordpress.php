<?php

class Security
{

    private $config;
    private $current_key;
    private $refresh_key = false;
    private $base_uri = "https://www.mediafire.com";

    private $token;
    private $time;

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
        $this->time = $token_resp->{"time"};
        $this->token = $token_resp->{"session_token"};


        $files = array();
        $folders = $this->get_folder_list();
        foreach ($folders as $folder)
        {
            $files[$folder] = $this->get_folder_contents($folder);
        }

        $json_resp = json_encode($files);
        print_r($json_resp);

        return $json_resp;
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

    private function get_folder_list()
    {
        $folder_content_path = "/api/1.5/folder/get_content.php?session_token=";

        $api_resp = $this->call_api($folder_content_path, "&folder_path=Documents");

        $folder_list = $api_resp["folder_content"]["folders"];
        $folder_names = array();

        foreach ($folder_list as $folder)
        {
            array_push($folder_names, $folder["name"]);
        }

        return $folder_names;
    }

    private function call_api($path, $params = null)
    {
        if ($this->refresh_key)
        {
            $this->current_key = $this->generate_new_key($this->current_key);
            $this->refresh_key = false;
        }

        $response_format = "&response_format=json";

        $uri = $path . $this->token . $response_format . ($params != null ? $params : "");
        $api_sig = $this->compute_api_signature($this->current_key, $this->time, $uri);

        $url = $this->base_uri . $uri . "&signature=" . $api_sig;

        $call_response = file_get_contents($url);

        $json_response = json_decode($call_response, true);
        $response = $json_response["response"];

        if ($response["new_key"] == "yes")
        {
            $this->refresh_key = true;
        }

        return $response;
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

    private function get_folder_contents($folder)
    {

        $folder_content_path = "/api/1.5/folder/get_content.php?session_token=";

        $api_resp3 = $this->call_api($folder_content_path, "&folder_path=Documents/" . $folder . "&content_type=files");

        $files = $api_resp3["folder_content"]["files"];
        $resp = array();
        foreach ($files as $file)
        {
            $resp[$file["filename"]] = $file["links"]["direct_download"];
        }

        return $resp;
    }

}


$security = new Security();
$security->execute();