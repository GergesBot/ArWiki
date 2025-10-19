SELECT
    i.img_name,
    i.img_size,
    i.img_height,
    i.img_width
FROM image AS i
JOIN page AS p ON p.page_title = i.img_name
JOIN categorylinks AS cl ON p.page_id = cl.cl_from
WHERE i.img_width > 400
  AND i.img_height > 400
  AND i.img_name NOT LIKE "%.svg"
  AND i.img_name NOT LIKE "%.pdf"
  AND p.page_is_redirect = 0
  AND p.page_namespace = 6
  AND cl.cl_to = "جميع_الملفات_غير_الحرة"
AND NOT EXISTS (
    SELECT 1
    FROM page AS pp
    JOIN categorylinks AS cl2 ON pp.page_id = cl2.cl_from
    WHERE pp.page_title = i.img_name
      AND cl2.cl_to = "ملفات_غير_حرة_موسومة_لعدم_تقليل_الدقة"
);
