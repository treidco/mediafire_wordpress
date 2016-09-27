<?php

/*
Plugin Name: MediaFire Wordpress
Description: Integration with MediaFire
Version:     1.0
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MediaFire Wordpress is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

MediaFire Wordpress is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with MediaFire Wordpress. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

//defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
require_once(dirname(__FILE__) . '/src/file_handling.php');

class PluginClass
{

    public function __construct($Object)
    {

        $test = add_shortcode('someShortcode', array(
            $Object,
            'latest'
        ));

        $library = add_shortcode('audioLibrary', array(
            $Object,
            'library'
        ));

    }

}

class MediaFire_WordPress
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
        $conf_file_path = "conf.txt";
        if (function_exists(plugin_dir_path) && file_exists(plugin_dir_path(__FILE__) . "conf.txt"))
        {
            $conf_file_path = plugin_dir_path(__FILE__) . "conf.txt";
        }
        $conf_file = fopen($conf_file_path, "r") or die("Unable to open file!");
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

    private function authenticate()
    {
        $token_resp = $this->get_session_token();

        $this->current_key = $token_resp->{"secret_key"};
        $this->time = $token_resp->{"time"};
        $this->token = $token_resp->{"session_token"};
    }

    public function latest()
    {

        $this->authenticate();

        $files = array();
        $folders = $this->get_folder_list("Audio");
        foreach ($folders as $folder)
        {
            $files[$folder] = $this->get_folder_contents($folder, "Audio");
        }

        $json_resp = json_encode($files);

        return $json_resp;
    }

    public function library()
    {
        $this->authenticate();

        $files = array();
        $folders = $this->get_folder_list("Archive");
        foreach ($folders as $folder)
        {
            $files[$folder] = $this->get_folder_contents($folder, "Archive");
        }

        $json_resp = json_encode($files);

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

    private function get_folder_list($path)
    {
        $folder_content_path = "/api/1.5/folder/get_content.php?session_token=";

        $api_resp = $this->call_api($folder_content_path, "&folder_path=" . $path);

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

    private function get_folder_contents($folder, $path)
    {

        $folder_content_path = "/api/1.5/folder/get_content.php?session_token=";

        $api_resp3 = $this->call_api($folder_content_path, "&folder_path=" . $path . "/" . $folder . "&content_type=files&order_direction=desc");
        $files = $api_resp3["folder_content"]["files"];

        $resp = array();
        foreach ($files as $file)
        {
            $filename = $file["filename"];
            $file_details = handle_file($filename);
            $file_details["link"] = $file["links"]["normal_download"];
            $resp[] = $file_details;
        }

        return $resp;
    }

}

$mfwp = new MediaFire_WordPress();
if (function_exists(add_shortcode))
{
    $Plugin = new PluginClass($mfwp);
}
else
{
    $mfwp->latest();
}


