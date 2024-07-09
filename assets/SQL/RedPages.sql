SELECT 
  REPLACE(linktarget.lt_title,"_"," ") as "page_title"
FROM pagelinks
JOIN page ON page.page_id = pagelinks.pl_from
JOIN linktarget ON lt_id = pl_target_id
WHERE page.page_title = "{{Name}}"
AND pagelinks.pl_from_namespace = {{FromNamespace}}
AND linktarget.lt_namespace = {{Namespace}}
AND linktarget.lt_title NOT IN (
SELECT red_page.page_title    
FROM page AS red_page
WHERE red_page.page_namespace = {{Namespace}});
