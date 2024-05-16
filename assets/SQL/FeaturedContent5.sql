SELECT
    COUNT(DISTINCT linktarget.lt_title) AS red_links
FROM pagelinks AS red_pagelinks
INNER JOIN page ON (red_pagelinks.pl_from = page.page_id)
INNER JOIN linktarget ON (red_pagelinks.pl_target_id = linktarget.lt_id)
WHERE page_title = "{{Name}}"
AND page_namespace = 0
AND linktarget.lt_namespace = 0
AND linktarget.lt_title NOT IN (
    SELECT red_page.page_title
    FROM page AS red_page
WHERE red_page.page_namespace = 0
)