SELECT COUNT(DISTINCT pl_from) AS to_links
FROM pagelinks
INNER JOIN linktarget ON (pl_target_id = linktarget.lt_id)
WHERE pl_from_namespace = 0
AND lt_namespace = 0
AND lt_title = "{{Name}}";