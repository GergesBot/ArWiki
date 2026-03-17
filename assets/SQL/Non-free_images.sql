SELECT
    i.img_name,
    i.img_size,
    i.img_height,
    i.img_width
FROM image AS i
JOIN page AS p ON p.page_title = i.img_name
JOIN categorylinks AS cl ON p.page_id = cl.cl_from
JOIN linktarget AS lt ON cl.cl_target_id = lt.lt_id
WHERE i.img_width > 400
  AND i.img_height > 400
  AND i.img_name NOT LIKE "%.svg"
  AND i.img_name NOT LIKE "%.pdf"
  AND p.page_is_redirect = 0
  AND p.page_namespace = 6
  AND lt.lt_namespace = 14
  AND lt.lt_title = "جميع_الملفات_غير_الحرة"
AND NOT EXISTS (
    SELECT 1
    FROM page AS pp
    JOIN categorylinks AS cl2 ON pp.page_id = cl2.cl_from
    JOIN linktarget AS lt2 ON cl2.cl_target_id = lt2.lt_id
    WHERE pp.page_title = i.img_name
      AND lt2.lt_namespace = 14
      AND lt2.lt_title = "ملفات_غير_حرة_موسومة_لعدم_تقليل_الدقة"
)
AND NOT EXISTS (
    SELECT 1
    FROM page AS pp
    JOIN categorylinks AS cl3 ON pp.page_id = cl3.cl_from
    JOIN linktarget AS lt3 ON cl3.cl_target_id = lt3.lt_id
    WHERE pp.page_title = i.img_name
      AND lt3.lt_namespace = 14
      AND lt3.lt_title = "ملفات_غير_حرة_بإصدارات_يتيمة"
);
