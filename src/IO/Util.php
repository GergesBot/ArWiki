<?php
namespace Bot\IO;

use WikiConnect\MediawikiApi\Client\Action\ActionApi;
use WikiConnect\MediawikiApi\Client\Action\Request\ActionRequest;
use DateTime;

class Util
{
    public static function ReadFile(string $File, array $Array = array()) {
        $FileStream = file_get_contents($File);
        foreach ($Array as $key => $value) {
            $FileStream = str_replace("{{" . $key . "}}", $value, $FileStream);
        }
        return $FileStream;
    }
    public static function getImageInfo(ActionApi $api, string $filename, string $iiprop = null): array | false {
        $query = [
            "format" => "json",
            "titles" => "File:$filename",
            "prop" => "imageinfo"
        ];

        if ($iiprop != null) {
            $query["iiprop"] = $iiprop;
        }
        $data = $api->request(ActionRequest::simpleGet("query", $query));
        if (isset($data["query"]["pages"]) && !empty($data["query"]["pages"])) {
            $firstPageKey = array_key_first($data["query"]["pages"]);
            $page = $data["query"]["pages"][$firstPageKey];
            if (isset($page["imageinfo"]) && isset($page["imageinfo"][0])) {
                if ($iiprop !== null) {
                    $page["imageinfo"][0]["iiprop"] = $iiprop;
                }
                return $page["imageinfo"][0];
            }
        }
        return false;
    }
    public static function getYearMonth(): string {
        $months = array(
            "January" => "يناير",
            "February" => "فبراير",
            "March" => "مارس",
            "April" => "أبريل",
            "May" => "مايو",
            "June" => "يونيو",
            "July" => "يوليو",
            "August" => "أغسطس",
            "September" => "سبتمبر",
            "October" => "أكتوبر",
            "November" => "نوفمبر",
            "December" => "ديسمبر"
        );
        return $months[date('F')] . " " . date('Y');
    }
    public static function PregReplace($Text, $Array = array()): string {
        foreach ($Array as $Row) {
            $Text = preg_replace($Row[0], $Row[1], $Text);
        }
        return $Text;
    }
    public static function calculateDaysFromToday($targetDate) {
        $months = array(
            "يناير" => "January",
            "فبراير" => "February",
            "مارس" => "March",
            "أبريل" => "April",
            "مايو" => "May",
            "يونيو" => "June",
            "يوليو" => "July",
            "أغسطس" => "August",
            "سبتمبر" => "September",
            "أكتوبر" => "October",
            "نوفمبر" => "November",
            "ديسمبر" => "December"
        );
        $today = new DateTime();
        $targetDateTime = new DateTime(str_replace(array_keys($months), array_values($months), $targetDate));

        $interval = $today->diff($targetDateTime);

        return $interval->d;
    }
    public static function getTextFromWikitext(string $text): string {
        // remove ref
        $text = preg_replace("/<ref([^\/>]*?)>(.+?)<\/ref>/is", "", $text);
        // remove sub templates
        $text = preg_replace("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", "", $text);
        // remove files
        $text = preg_replace("/\[\[(ملف:|File:).*\]\]/u", "", $text);
        // remove
        $text = preg_replace("/\[\[(تصنيف:|Category:).*\]\]/u", "", $text);
        // remove headlines
        $text = preg_replace("/^={1,6}(.*?)={1,6}$/um", "$1", $text);
        // remove tables
        $text = preg_replace("/{\|[\s\S]*?\|}/", "", $text);
        // remove template
        $text = preg_replace("/\{\{[\s\S]*?\}\}/", "", $text);
        // remove html tag include ref tags
        $text = preg_replace("/<[^>]+>/is", "", $text);
        // remove all comments
        $text = preg_replace("/<!--.*?-->/", "", $text);
        // remove all external links
        $text = preg_replace("/\[http[^\]]+\]/", "", $text);
        // replace all wikilinks to be like [from|some text ] to from
        $text = preg_replace("/\[\[([^\]\|]+?)\|([^\]]+?)\]\]/u", "$2", $text);
        $text = preg_replace("/\[\[([^\]]+?)\]\]/u", "$1", $text);
        // remove tables like this "{| |}"
        $text = preg_replace("/{\|[\s\S]*?\|}/", "", $text);
        return $text;
    }
    public static function replace(string $pattern, string $replacement, string $string, ?string $options = null): string|false|null {
        mb_regex_encoding('UTF-8');
        $matches = [];
        if (mb_ereg($pattern, $string, $matches)) {
            foreach ($matches as $key => $value) {
                $replacement = str_replace("$" . $key, $value, $replacement);
            }
        }
        return mb_ereg_replace($pattern, $replacement, $string, $options);
    }
}