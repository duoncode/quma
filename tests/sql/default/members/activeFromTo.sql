SELECT
    member, name, joined, left
FROM
    members
WHERE
    joined >= :from
    AND left <= :to;
