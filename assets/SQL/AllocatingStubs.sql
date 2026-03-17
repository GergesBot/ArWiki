SELECT
    REPLACE(p.page_title, "_", " ") AS title,
    REPLACE(REPLACE(REPLACE(GROUP_CONCAT(lt2.lt_title), "_", " "),"بوابة ",""),"/مقالات متعلقة","") AS portals
FROM
    page AS p
JOIN categorylinks AS cl1 ON p.page_id = cl1.cl_from
JOIN linktarget AS lt1 ON cl1.cl_target_id = lt1.lt_id
JOIN categorylinks AS cl2 ON p.page_id = cl2.cl_from
JOIN linktarget AS lt2 ON cl2.cl_target_id = lt2.lt_id
WHERE p.page_is_redirect = 0
AND p.page_namespace = 0
AND lt1.lt_namespace = 14
AND lt1.lt_title = "بذرة"
AND lt2.lt_namespace = 14
AND lt2.lt_title LIKE "%/مقالات_متعلقة"
GROUP BY p.page_title