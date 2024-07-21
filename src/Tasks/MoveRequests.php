<?php
namespace Bot\Tasks;

use WikiConnect\MediawikiApi\Client\Action\Request\ActionRequest;
use WikiConnect\MediawikiApi\Client\Action\Exception\UsageException;
use WikiConnect\MediawikiApi\DataModel\Page;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use WikiConnect\MediawikiApi\DataModel\Title;
use Bot\Service\Wikidata;
use Bot\IO\Util;
use Exception;


class MoveRequests extends Task
{
    private function getRequests(string $text): array {
        $lines = explode("\n", $text);
        $requests = [];
        foreach ($lines as $line) {
            $match = @preg_match("/\[\[:(.*)\]\]>\[\[:(.*)\]\]/u", $line, $matches);
            if ($match === false) {
                $error = preg_last_error();
                if ($error == PREG_INTERNAL_ERROR) {
                    $this->log->error("Invalid regular expression.", [$error]);
                }
            } elseif ($match) {
                $requests[] = [
                    "from" => $matches[1],
                    "to" => $matches[2]
                ];
            }
        }
        return $requests;
    }
    private function userCheck(string $username): bool {
        $whitelist = ["Gerges"];
        if (in_array($username, $whitelist)) {
            return true;      
        } else {
            $user = $this->services->newUserGetter()->getFromUsername($username);
            return in_array("sysop", $user->getGroups());
        }
    }
    private function getSummary(): string {
        return preg_replace("/<!--.*?-->/", "", trim($this->readPage("ويكيبيديا:طلبات نقل عبر البوت/ملخص التعديل")));
    }
    private function getTalkPage(string $title): string {
        $namespaces = [
            "مستخدم" => "نقاش المستخدم",
            "ويكيبيديا" => "نقاش ويكيبيديا",
            "وب" => "نقاش ويكيبيديا",
            "ملف" => "نقاش الملف",
            "ميدياويكي" => "نقاش ميدياويكي",
            "قالب" => "نقاش القالب",
            "مساعدة" => "نقاش المساعدة",
            "تصنيف" => "نقاش التصنيف",
            "بوابة" => "نقاش البوابة",
            "كتب" => "نقاش الكتب",
            "نص زمنيTimedText" => "نقاش النص الزمنيTimedText talk",
            "وحدة" => "نقاش الوحدة"
        ];
        if (!str_contains($title, ":")) {
            return "نقاش:${title}";
        }
        $data = explode(":", $title);
        if (array_key_exists($data[0], $namespaces)) {
            return $namespaces[$data[0]] . ":" . $data[1];
        } else {
            return false;
        }
        return $namespaces[$data[0]] . ":" . $data[1];
    }
    private function getSittings(): array {
        $text = $this->readPage("ويكيبيديا:طلبات نقل عبر البوت/خيارات البوت");
        return [
            "movesubpages" => $this->getValueTemplate($text, "move-subpages") == "yes",
            "noredirect" => $this->getValueTemplate($text, "leave-redirect") == "no",
            "movetalk" => $this->getValueTemplate($text, "move-talk") == "yes",
            "leave-talk" => $this->getValueTemplate($text, "leave-talk") == "yes",
            "rename-item" => $this->getValueTemplate($text, "rename-item") == "yes"
        ];
    }
    private function removeDisambiguation(string $input): string {
        $pattern = "/\([^)]*?\)/u";
        $input = preg_replace($pattern, "", $input);
        $input = preg_replace("/\s+/", " ", $input);
        return $input;
    }
    private function renameItem(string $item, string $newname): void {
        $wikidata = Wikidata::getInstance();
        $wbFactory = $wikidata->getFactory();
        $wbGeter = $wbFactory->newRevisionGetter();
        $wbSaver = $wbFactory->newRevisionSaver();
        $wbItem = $wbGeter->getFromId($item);
        $wbItem->getContent()->getData()->setLabel("ar" , $newname);
        $wbSaver->save($wbItem);
        
    }

    private function move(string $from, string $to): void {
        $mover = $this->services->newPageMover();
        $page = $this->getPage($from);
        $target = new Title($to);
        $reason = $this->getSummary();
        $sittings = $this->getSittings();
        $params = ["reason" => $reason];
        if ($sittings["noredirect"]) {
            $params["noredirect"] = true;
        }
        if ($sittings["movesubpages"]) {
            $params["movesubpages"] = true;
        }
        if ($sittings["leave-talk"]) {
            if ($sittings["movetalk"]) {
                $params["movetalk"] = true;
            }
        }
        try {
            $this->log->info("Move requests: Moving from $from to $to with parameters: " . print_r($params, true));
            if ($sittings["rename-item"]) {
                $item = $this->getItem($from);
                if ($item != false) {
                    $this->log->info("Move requests: Renaming item $item to $to");
                    $this->renameItem($item, $to);
                }
            }
            if ($sittings["leave-talk"]) {
                $mover->move($page, $target, $params);
            } else {
                $mover->move($page, $target, $params);
                if ($this->pageExists($this->getTalkPage($from))) {
                    $params["noredirect"] = true;
                    $mover->move($this->getPage($this->getTalkPage($from)), new Title($this->getTalkPage($to)), $params);
                }
            }
        } catch (UsageException $error) {
            $this->log->error("Move requests: An error occurred to move from $from to $to", [$error->getRawMessage()]);
        }

    }
    private function clearRequests(): void {
        $page = $this->getPage("ويكيبيديا:طلبات نقل عبر البوت");
        $content = new Content("{{/مقدمة}}");
        $revision = new Revision($content, $page->getPageIdentifier());
        $editInfo = new EditInfo("بوت: انتهت عملية النقل", true, true);
        $this->services->newRevisionSaver()->save($revision, $editInfo);
    }
    private function init(): void {
        $page = $this->getPage("ويكيبيديا:طلبات نقل عبر البوت");
        $latestUser = $page->getRevisions()->getLatest()->getUser();
        if ($this->userCheck($latestUser)) {
            $text = $this->readPage($page);
            $requests = $this->getRequests($text);
            if (!empty($requests)) {
                foreach ($requests as $request) {
                    $this->move($request["from"], $request["to"]);
                    sleep(1);
                }
                $this->clearRequests();
            }
        } else {
            $this->log->info("The last user: $latestUser is not an sysop.");
        }

    }
    public function RUN(): void {
        $this->running(function() {
            $this->init();
        });
    }
}