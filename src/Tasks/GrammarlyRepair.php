<?php
namespace Bot\Tasks;

use WikiConnect\MediawikiApi\Client\Action\Exception\UsageException;
use WikiConnect\MediawikiApi\DataModel\Page;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use Bot\IO\Util;
use Exception;


class GrammarlyRepair extends Task
{

    private function SeparatorRepair(string $text): string {
        $replacements = array(
            "/([\p{Arabic}+|\]|\}]),([\p{Arabic}+|\[])/" => "$1، $2",
            "/([\p{Arabic}+|\]|\}])،([\p{Arabic}+|\[])/" => "$1، $2",
            "/([\p{Arabic}+|\]|\}]) ، ([\p{Arabic}+|\[])/" => "$1، $2",
            "/([\p{Arabic}+|\]|\}]) ،([\p{Arabic}+|\[])/" => "$1، $2"
        );
        $str = $text;
        foreach ($replacements as $pattern => $replacement) {
            $str = preg_replace($pattern."u", $replacement, $str);
        }
        return $str;
    }
    private function Repair(string $text, array $replacements): string {
        /* It no longer uses a local file, but rather a Wikipedia file
        $replacements = json_decode(Util::ReadFile(FOLDER_JSON . "/replacements.json"));
        */
        $str = $text;
        foreach ($replacements as $pattern => $replacement) {
            $pattern = "/".$pattern."((?![^\{]*\})(?![^\[]*\])(?![^<]*<\/.*>)(?![\p{M}]))/u";
            if (preg_match_all($pattern, $str, $matches)) {
                $str = $this->SeparatorRepair($str);
                $str = preg_replace($pattern, $replacement, $str);
            }
        }
        return $str;
    }
    public function RunRepair(string $name, array $replacements): void {
        $page = $this->services->newPageGetter()->getFromTitle($name);
        $text = $page->getRevisions()->getLatest()->getContent()->getData();
        if ($this->allowBots($text, "GergesBot")) {
            $reformedText = $this->Repair($text, $replacements);
            if ($text != $reformedText) {
                $content = new Content($reformedText);
                $editInfo = new EditInfo("بوت: تدقيق لغوي", true, true);
                $revision = new Revision($content, $page->getPageIdentifier());
                $this->services->newRevisionSaver()->save($revision, $editInfo);
                $this->log->info("grammatical errors were corrected on this page ${name}.");
            }
        } else {
            $this->log->info("This is a page $name that the bot is prohibited from modifying ");
        }
    }
    private function init() {
        $replacements = json_decode($this->readPage("MediaWiki:Ar gram errors.json"), true);
        $query = $this->query->getArray(Util::ReadFile(FOLDER_SQL . "/getPages_GrammarlyRepair.sql", [
            "LIMIT" => 5000,
            "OFFSET" => 0
        ]));
        
        $pages = array_column($query, "page_title");
        foreach ($pages as $page) {
            try {
                $this->RunRepair($page, $replacements);
            } catch (UsageException $error) {
                $this->log->debug("An unexpected error occurred while I was working on $page", [$error->getRawMessage()]);
            }
        }
    }
    public function RUN(): void {
        $this->running(function() {
            $this->init();
        });
    }
}