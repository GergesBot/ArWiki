<?php

namespace Bot\Tasks;

use mysqli;
use WikiConnect\MediawikiApi\MediawikiFactory;
use WikiConnect\MediawikiApi\Client\Action\ActionApi;
use WikiConnect\MediawikiApi\DataModel\Page;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Bot\IO\QueryDB;
use Throwable;
use Exception;

abstract class Task {

    public ActionApi $api;
    public MediawikiFactory $services;
    public QueryDB $query;
    public mysqli $mysqli;
    public Logger $log;
    public bool $local;
	
	public function __construct(ActionApi $api, MediawikiFactory $services, mysqli $mysqli, bool $local = false) {
        $this->api = $api;
        $this->services = $services;
        $this->query = new QueryDB($mysqli);
        $this->mysqli = $mysqli;
        $this->log = new Logger("Task");
        $this->local = $local;
        $this->streamLogger();
    }
    public function streamLogger(): void {
        if ($this->local) {
            $day = date("d-M-Y");
            $this->log->pushHandler(new TestHandler());
            $this->log->pushHandler(new StreamHandler("php://stdout"));
            $this->log->pushHandler(new StreamHandler(FOLDER_LOGS . "/" . str_replace("Bot\\Tasks\\","",get_class($this)) . "/log-{$day}.log"));
        } else {
            $day = date("d-M-Y");
            $this->log->pushHandler(new StreamHandler(FOLDER_LOGS . "/" . str_replace("Bot\\Tasks\\","",get_class($this)) . "/log-{$day}.log"));
        }
    }
    
    public function getPage(string $name): Page {
        return $this->services->newPageGetter()->getFromTitle($name);
    }
    public function readPage(string | Page $page): string {
        if (is_string($page)) {
            return $this->getPage($page)->getRevisions()->getLatest()->getContent()->getData();
        } else {
            return $page->getRevisions()->getLatest()->getContent()->getData();
        }
    }
    
    public function pageExists(string | Page $title): bool {
        if (is_string($title)) {
            $page = $this->getPage($title);
        } else {
            $page = $title;
        }
        return $page->getPageIdentifier()->getId() >= 1;
    }
    public function allowBots(string $text, string $user ): bool {
        if (preg_match("/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?".preg_quote($user,"/").".*?)}}/iS",$text))
            return false;
        if (preg_match("/\{\{(bots\|allow=all|bots\|allow=.*?".preg_quote($user,"/").".*?)}}/iS", $text))
            return true;
        if (preg_match("/\{\{(bots\|allow=.*?)}}/iS", $text))
            return false;
        return true;
    }
    public function getValueTemplate(string $text, string $key): string | int {
        if(@preg_match("/".preg_quote($key).".*?=(.*)/u", $text, $matche) === false){
            throw new Exception(preg_last_error_msg());
        }
        return trim($matche[1]);
    }
    public function running($fun): void {
        try{
            call_user_func($fun);
            $this->log->info("Task ".str_replace("Bot\\Tasks\\","",get_class($this))." succeeded to execute.");
        } catch (Throwable $error) {
            $this->log->debug("Task ".str_replace("Bot\\Tasks\\","",get_class($this))." failed to execute.", [$error->__toString()]);
        }
    }

}
