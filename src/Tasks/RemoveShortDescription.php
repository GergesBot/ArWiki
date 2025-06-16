<?php

namespace Bot\Tasks;

use Bot\IO\Util;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;

class RemoveShortDescription extends Task
{
    private array $templates = [
        'وصف قصير',
        'وصف مختصر',
        'Short description'
    ];

    private function RunRemover(string $name) {
        $page = $this->services->newPageGetter()->getFromTitle($name);
        $text = $page->getRevisions()->getLatest()->getContent()->getData();
        $reformedText = $text;
        foreach ($this->templates as $template) {
            $pattern = "/\{\{".preg_quote($template, "/")."\|\s*.*?\}\}(\n?)/u";
            if (preg_match($pattern, $text)) {
                $this->log->info("The ${template} template is removed from the ${name} page.");
                $reformedText = preg_replace($pattern, "", $reformedText);
            }
        }
        if ($text != $reformedText) {
            $content = new Content($reformedText);
            $editInfo = new EditInfo("بوت: إزالة وصف قصير", true, true);
            $revision = new Revision($content, $page->getPageIdentifier());
            $this->services->newRevisionSaver()->save($revision, $editInfo);
            $this->log->info("The bot removed short description from the ${name} page.");
        }
    }
    private function init()
    {
        $OFFSET = 1;
        while (true) {
            $pages = array_column($this->query->getArray(Util::ReadFile(FOLDER_SQL . "/PagesWithShortDescription.sql", [
                "LIMIT" => 100,
                "OFFSET" => $OFFSET
            ])), "page_title");
            if (empty($pages)) {
                break;
            }
            foreach ($pages as $page) {
                $this->RunRemover($page);
            }
            $OFFSET = +100;
        }
    }

    public function RUN(): void
    {
        $this->running(function () {
            $this->init();
        });
    }
}
