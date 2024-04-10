<?php
namespace Bot\Tasks\Maintenence;

use WikiConnect\MediawikiApi\DataModel\Page;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use Bot\IO\Util;
use Bot\Tasks\Task;

class DeleteRedirectTalkPages extends Task
{   
    private function addCSD(string $name): void {
        $page = $this->getPage($name);
        $text = $this->readPage($page);
        $content = new Content("{{شطب عبر البوت|تحويلة لصفحة نقاش}}\n${text}");
        $editInfo = new EditInfo("بوت صيانة: طلب شطب تحويلة لصفحة نقاش", true, true);
        $revision = new Revision($content, $page->getPageIdentifier());
        $this->services->newRevisionSaver()->save($revision, $editInfo);
        $this->log->info("add CSD to ${name}.");
    }
    private function delete(string $name): void {
        $page = $this->getPage($name);
        $this->services->newPageDeleter()->delete($page, [
            "reason" => "تحويلة لصفحة نقاش"
        ]);
        $this->log->info("Page ${name} has been deleted.");
    }
    public function Deleter(): void {
        $this->running(function() {
            $query = $this->query->getArray(Util::ReadFile(FOLDER_SQL . "/RedirectTalkPages.sql"));
            $pages = array_column($query, "page_title");
            foreach ($pages as $page) {
                $this->delete($page);
            }
        });
    }
    public function RUN(): void {
        $this->running(function() {
            $query = $this->query->getArray(Util::ReadFile(FOLDER_SQL . "/RedirectTalkPages.sql"));
            $pages = array_column($query, "page_title");
            foreach ($pages as $page) {
                $this->addCSD($page);
            }
        });
    }
}