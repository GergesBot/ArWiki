<?php
namespace Bot\Tasks;

use RuntimeException;
use WikiConnect\MediawikiApi\Client\Action\Exception\UsageException;
use WikiConnect\MediawikiApi\DataModel\Page;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use Bot\IO\Util;

class PeerReviewArchive extends Task
{

    private function getPagesRejected(): array {
        return array_map(function($text) {
            $prefix = "مراجعة الزملاء/";
            return str_replace($prefix, "", $text);
        }, array_column($this->query->getArray(Util::ReadFile(FOLDER_SQL . "/GetPagesFromCategories.sql", [
            "Name" => "مراجعات_الزملاء_المرفوضة",
        ])), "page_title"));
    }
    private function getPagesCurrent() {
        $page = $this->services->newPageGetter()->getFromTitle("ويكيبيديا:مراجعة الزملاء");
        $text = $page->getRevisions()->getLatest()->getContent()->getData();
        preg_match_all("/\{\{متمز\|(.*)\}\}/U", $text, $matches);
        return $matches[1];
    }
    private function getTagType($text): string {
        if (preg_match("/نوع الترشيح =(.*)/u", $text, $matches)) {
            return trim($matches[1]);
        } else {
            throw new RuntimeException("Error: The tag type is not known.");
        }
    }
    private function getReviewStatus($text): bool {
        if (preg_match("/حالة المراجعة =(.*)/u", $text, $matches)) {
            if ("مقبولة" == trim($matches[1])){
                return true;
            }
            return false;
        } else {
            throw new RuntimeException("Error: The Review Status type is not known.");
        }
    }
    private function RemoveFromIRP($name): void {
        $page = $this->services->newPageGetter()->getFromTitle("ويكيبيديا:مراجعة الزملاء");
        $text = $page->getRevisions()->getLatest()->getContent()->getData();
        $revision = new Revision(new Content(str_replace("\n{{متمز|$name}}", "", $text)),$page->getPageIdentifier());
        $this->services->newRevisionSaver()->save($revision, new EditInfo("بوت: أرشفة.", true,  true));
    }
    private function getTemplate_FormatReviewPage($tag): string {
        switch ($tag) {
            case "مختارة":
                return "مقالة مرشحة لوسم مختارة";
            case "جيدة":
                return "مقالة مرشحة لوسم جيدة";
            case "قائمة":
                return "قائمة مرشحة لوسم مختارة";
            default:
                throw new RuntimeException("Error: Tag type is wrong.");
        }
    }
    private function getEnd($text): string {
        if (preg_match("/تاريخ انتهاء المراجعة =(.*)/u", $text, $matches)) {
            return trim($matches[1]);
        } else {
            throw new RuntimeException("Error: The Review end date is not known.");
        }
    }
    private function FormatReviewPage($page, $tag): void {
        $text = $page->getRevisions()->getLatest()->getContent()->getData();
        $status = $this->getReviewStatus($text);
        $_status = $status ? "تم" : "لم يتم";
        $template = $this->getTemplate_FormatReviewPage($tag);
        $text = str_replace("</onlyinclude>", "", $text);
        $text = preg_replace(
            "/\{\{تفاصيل مراجعة الزملاء(.*)\}\}$/s",
            "{{تفاصيل مراجعة الزملاء$1}}\n{{".$template."/ذيل التصويت}}\n</onlyinclude>",
            $text,
        );
        $text = str_replace(
            "{{طلب مراجعة الزملاء",
            "{{".$template."/رأس التصويت|${_status}}}\n{{طلب مراجعة الزملاء",
            $text,
        );
        $revision = new Revision(new Content($text),$page->getPageIdentifier());
        $this->services->newRevisionSaver()->save($revision, new EditInfo("بوت: تنسيق الصفحة.", true,  true));
    }
    
    public function Archive($name): void {
        
            $Page = $this->getPage("ويكيبيديا:مراجعة الزملاء/${name}");
            $TextPage = $Page->getRevisions()->getLatest()->getContent()->getData();
            $ReviewEndDate = $this->getEnd($TextPage);
            $Tag = $this->getTagType($TextPage);
            if(Util::calculateDaysFromToday($ReviewEndDate) >= 1){
                $this->RemoveFromIRP($name);
                $this->FormatReviewPage($Page, $Tag);
                $num = 33;
                $YearMonth = Util::getYearMonth();
                $revision = null;
                while (true) {
                    $ArchivePage = $this->getPage("ويكيبيديا:مراجعة الزملاء/أرشيف ${num}");
                    if ($ArchivePage->getPageIdentifier()->getId() < 1) {
                        $newArchivePage = $this->getPage("ويكيبيديا:مراجعة الزملاء/أرشيف ${num}");
                        $this->services->newRevisionSaver()->save(new Revision(new Content("{{ويكيبيديا:مراجعة الزملاء/تبويب}}\n{{أرشيف مراجعة الزملاء\n| 1 = ${num}\n}}"),$newArchivePage->getPageIdentifier()), new EditInfo("بوت: إنشاء أرشيف.", true,  true));
                     }
                    $ArchivePage = $this->getPage("ويكيبيديا:مراجعة الزملاء/أرشيف ${num}");
                    $ArchivePageText = $ArchivePage->getRevisions()->getLatest()->getContent()->getData();
                    if (preg_match_all("/\{\{متمز\|(.*)\}\}/", $ArchivePageText, $matches) < 11) {
                        if (strpos($ArchivePageText, $YearMonth) !== false) {
                            $revision = new Revision(new Content("${ArchivePageText}\n{{متمز|$name}}"),$ArchivePage->getPageIdentifier());
                        } else {    
                            $revision = new Revision(new Content("${ArchivePageText}\n== ${YearMonth} ==\n{{متمز|$name}}"),$ArchivePage->getPageIdentifier());
                        }
                        break;
                    }
                    $num++;
                }
                $this->services->newRevisionSaver()->save($revision, new EditInfo("بوت: أرشفة.", true,  true));
            }
        
        
    }
    public function RUN(): void {
        $this->running(function(){
            $pages1 = $this->getPagesRejected();
            $pages2 = $this->getPagesCurrent();
            $pages = array_intersect($pages1, $pages2);
            
            foreach ($pages as $page) {
                $this->log->info("Task PeerReviewArchive: Work is done on a page ${page}.");
                $this->Archive($page);
            }
        
        });
    }
}
?>
