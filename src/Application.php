<?php

namespace Bot;

//Set constants
define("FOLDER_TMP", dirname(__DIR__) . "/tmp");
define("FOLDER_LOGS", dirname(__DIR__) . "/logs");
define("FOLDER_ASSETS", dirname(__DIR__) . "/assets");
define("FOLDER_SQL", dirname(__DIR__) . "/assets/SQL");
define("FOLDER_JSON", dirname(__DIR__) . "/assets/JSON");
define("FOLDER_SPARQL", dirname(__DIR__) . "/assets/SPARQL");

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use WikiConnect\MediawikiApi\Client\Action\ActionApi;
use WikiConnect\MediawikiApi\Client\Auth\UserAndPassword;
use WikiConnect\MediawikiApi\MediawikiFactory;
use Bot\Service\Wikidata;
use mysqli;

class Application
{
    private $api;
    private $services;
    private $mysqli;
    private $cookieFile;
    private $client;
    private $config;
    private static $instance = null;

    public static function getInstance(): self {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function __construct()
    {
        $this->createFolders();
        $this->config = parse_ini_file(".env");
        $this->cookieFile = FOLDER_TMP . "/.cookies";
        $this->client = new Client([
            "cookies" => new FileCookieJar($this->cookieFile, true)
        ]);

        $this->api = new ActionApi($this->config["apibot"], new UserAndPassword($this->config["userbot"], $this->config["passwordbot"]), $this->client);
        $this->services = new MediawikiFactory($this->api);
        $this->mysqli = new mysqli(
            $this->config["hostdb"],
            $this->config["userdb"],
            $this->config["passworddb"],
            $this->config["namedb"]
        );

        Wikidata::initialize(new UserAndPassword($this->config["userbot"], $this->config["passwordbot"]), $this->client);
        if (file_exists($this->cookieFile)) {
            chmod($this->cookieFile, 0600);
        }
    }

    private function createFolders() {
        $folders = [
            FOLDER_ASSETS,
            FOLDER_SQL,
            FOLDER_TMP,
            FOLDER_LOGS,
            FOLDER_SPARQL,
            FOLDER_JSON
        ];

        foreach ($folders as $folder) {
            if (!is_dir($folder)) {
                mkdir($folder);
            }
        }
    
    }
    public function getApi(): ActionApi
    {
        return $this->api;
    }

    public function getServices(): MediawikiFactory
    {
        return $this->services;
    }

    public function getMysqli(): mysqli
    {
        return $this->mysqli;
    }
}
