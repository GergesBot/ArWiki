SELECT
    REPLACE(page.page_title, '_', ' ') AS page_title
FROM
    page
JOIN
    categorylinks on page.page_id = categorylinks.cl_from
JOIN
    linktarget on categorylinks.cl_target_id = linktarget.lt_id
WHERE linktarget.lt_namespace = 14
    AND linktarget.lt_title like "{{Name}}";