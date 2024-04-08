SELECT CONCAT("نقاش:",p.page_title) AS page_title
FROM page AS p
WHERE p.page_is_redirect = 1
AND p.page_namespace = 1