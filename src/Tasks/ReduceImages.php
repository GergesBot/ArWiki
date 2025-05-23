<?php
namespace Bot\Tasks;

use WikiConnect\MediawikiApi\Client\Action\Exception\UsageException;
use WikiConnect\MediawikiApi\DataModel\Content;
use WikiConnect\MediawikiApi\DataModel\Revision;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use Bot\IO\Util;
use Bot\IO\ReduceImage;
use Bot\IO\Logger;
use Bot\Service\FileUploader;
use Exception;
use ImagickException;

class ReduceImages extends Task
{

    private function getImages() : array {
        return $this->query->getArray(Util::ReadFile(FOLDER_SQL . "/Non-free_images.sql"));
    }
    private function getMIMEType($content) : string {
        if ($content !== false) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_buffer($finfo, $content);
            finfo_close($finfo);
            return $mime_type;
        }
        return "";
    }
    private function checkMIMEType($mime_type) : bool {
        if (in_array($mime_type, array("image/jpeg", "image/png", "image/gif", "image/bmp", "image/webp", "image/tiff"))) {
            return true;
        }
        return false;
    }
    public function ReduceImage(string $filename, int $width, int $height) : void {
        $this->log->info("The bot reduces the file ${filename} size.");
        $ImageInfo = Util::getImageInfo($this->api, $filename, "url");

        $imageData = file_get_contents($ImageInfo["url"], false, stream_context_create([
            'http' => [
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
            ],
        ]));
        $MIMEType = $this->getMIMEType($imageData);

        if ($this->checkMIMEType($MIMEType)) {
            $reducer = new ReduceImage($imageData);
            $reducer->reduce(400, $filename);
            $fileUploader = new FileUploader($this->api);
            if ($fileUploader->upload(
                $filename,
                fopen(FOLDER_TMP."/".$filename, "r"),
                "",
                "بوت: تصغير حجم الصور غير حرة",
                "preferences",
                true
            )) {
                $this->log->info("File ${filename} uploaded successfully.");
            } else {
                $this->log->info("The file ${filename} was not uploaded.");
            }
        } else {
            $this->log->warning("The file ${filename} format ${MIMEType} is not supported.");
        }
    }
    public function removeFile($filename) : void {
        if (file_exists(FOLDER_TMP."/${filename}")) {
            // Check if the file exists before attempting to delete
            if (unlink(FOLDER_TMP."/${filename}")) {
                $this->log->info("File ${filename} deleted successfully.");
            } else {
                $this->log->info("Unable to delete the file ${filename}.");
            }
        } else {
            $this->log->info("File ${filename} does not exist.");
        }

    }
    public function appendONFR($filename) : void {
        $page = $this->getPage("ملف:${filename}");
        $text = $this->readPage($page);
        $ONFR = "{{نسخ:إصدارات غير حرة يتيمة}}\n";
        $revision = new Revision(new Content($ONFR . $text), $page->getPageIdentifier());
        $editInfo = new EditInfo("بوت: إصدار ملف غير حر يتيم", true,  true);
        $this->services->newRevisionSaver()->save($revision, $editInfo);
    }
    public function RUN(): void {
        $this->running(function(){
            $images = $this->getImages();
            $i = 0;
            foreach ($images as $image) {
                $this->ReduceImage($image["img_name"], $image["img_width"], $image["img_height"]);
                $this->removeFile($image["img_name"]);
                $this->appendONFR($image["img_name"]);
                $i++;
            }
        });
    }
}
