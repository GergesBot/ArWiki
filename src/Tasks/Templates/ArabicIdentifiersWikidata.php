<?php
namespace Bot\Tasks\Templates;

use WikiConnect\MediawikiApi\DataModel\Page;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use Bot\IO\Util;
use Bot\Service\WikibaseQuery;
use Bot\Tasks\Task;
use Exception;
use RuntimeException;

class ArabicIdentifiersWikidata extends Task
{
    private function savePage(Page $page, string $text, string $summary = "بوت: تحديث"): void {
        $content = new Content($text);
        $revision = new Revision($content, $page->getPageIdentifier());
        $editInfo = new EditInfo($summary, true, true);
        $this->services->newRevisionSaver()->save($revision, $editInfo);
    }
    public function init(): void {
        $this->running(function() {
            $page = $this->getPage("قالب:معرفات عربية في ويكي بيانات");
            $text = $this->readPage($page);

            if (preg_match_all("/\{\{خاصية\|(.*)\}\}/u", $text, $matches)) {
                $wikibaseQuery = new WikibaseQuery("https://query.wikidata.org/bigdata/namespace/wdq/sparql");
                $properties = $matches[1];
                foreach ($properties as $property) {
                    $count = count($wikibaseQuery->query(Util::ReadFile(FOLDER_SPARQL . "/getProperty.sparql", [
                        "Property" => $property
                    ]))["results"]["bindings"]);
                    $text = preg_replace("/(\* \{\{خاصية\|${property}\}\})( \d+)?/u", "$1 ${count}", $text);
                }

                $this->savePage($page, $text);

            } else {
                throw new RuntimeException("An error occurred while getting properties.");
            }

        });
    }
    public function RUN(): void {
        $this->running(function() {
            $this->init();
        });
    }
}