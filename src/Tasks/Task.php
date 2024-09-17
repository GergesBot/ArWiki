<?php

namespace Bot\Tasks;

use mysqli;
use WikiConnect\MediawikiApi\MediawikiFactory;
use WikiConnect\MediawikiApi\Client\Action\ActionApi;
use WikiConnect\MediawikiApi\DataModel\Page;
use WikiConnect\MediawikiApi\Client\Action\Request\ActionRequest;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Bot\IO\QueryDB;
use Throwable;
use Exception;
use RuntimeException;

abstract class Task {

    public ActionApi $api;
    public MediawikiFactory $services;
    public QueryDB $query;
    public mysqli $mysqli;
    public Logger $log;
	
	public function __construct(ActionApi $api, MediawikiFactory $services, mysqli $mysqli) {
        $this->api = $api;
        $this->services = $services;
        $this->query = new QueryDB($mysqli);
        $this->mysqli = $mysqli;
        $this->log = new Logger("Task");
        $this->setupLogging();
    }
    public function setupLogging(): void
    {
        $logDirectory = FOLDER_LOGS . '/' . str_replace('Bot\\Tasks\\', '', static::class);
        $logFile = $logDirectory . '/log-' . date('d-M-Y') . '.log';

        $this->log->pushHandler(new StreamHandler($logFile));

        if (isset($_ENV['XLOGS']) && $_ENV['XLOGS']) {
            $this->log->pushHandler(new TestHandler());
            $this->log->pushHandler(new StreamHandler('php://stdout'));
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
    public function getItem(string | Page $page): string  | false {
        if (is_string($page)) {
            $title = $page;
        } else {
            $title = $page->getPageIdentifier()->getTitle();
            if ($title === null) {
                throw new RuntimeException("Title is null for page: " . $page->getPageIdentifier()->getId());
            }
            $title = $title->getText();
        }
        $result = $this->api->request(
            ActionRequest::simpleGet(
                "query",
                [
                    "prop" => "pageprops",
                    "ppprop" => "wikibase_item",
                    "titles" => $title,
                    "formatversion" => "2"
                ]
            )
        );
        $pages = $result["query"]["pages"] ?? null;
        if ($pages === null || count($pages) === 0) {
            return false;
        }
        $pageProps = $pages[0]["pageprops"] ?? null;
        if ($pageProps === null || !isset($pageProps["wikibase_item"])) {
            return false;
        }
        return $pageProps["wikibase_item"];
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
