SELECT
    COUNT(DISTINCT linktarget.lt_title) AS blue_links
FROM pagelinks AS blue_pagelinks
INNER JOIN page ON (blue_pagelinks.pl_from = page.page_id)
INNER JOIN linktarget ON (blue_pagelinks.pl_target_id = linktarget.lt_id)
WHERE page_title = "{{Name}}"
AND page_namespace = 0
AND linktarget.lt_namespace = 0
AND linktarget.lt_title IN (
    SELECT blue_page.page_title
    FROM page AS blue_page
WHERE blue_page.page_namespace = 0
)