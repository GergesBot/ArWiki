SELECT
    page.page_title AS page_title,
    COUNT(DISTINCT linktarget.lt_title) AS disambiguation_links
FROM
    page
INNER JOIN pagelinks ON (pagelinks.pl_from = page.page_id)
INNER JOIN linktarget ON (pagelinks.pl_target_id = linktarget.lt_id)
WHERE page_title = "{{Name}}"
AND page.page_namespace = 0
AND linktarget.lt_namespace = 0
AND linktarget.lt_title like "%(توضيح)%"