SELECT DISTINCT page.page_title
FROM page
JOIN templatelinks ON templatelinks.tl_from = page.page_id
JOIN linktarget ON templatelinks.tl_target_id = linktarget.lt_id
WHERE page.page_namespace = 0
AND linktarget.lt_namespace = 10
AND linktarget.lt_title IN (
  'وصف_قصير', 'وصف_مختصر', 'Short_description'
)
GROUP BY page.page_title
LIMIT {{LIMIT}}
OFFSET {{OFFSET}};