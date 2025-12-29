 SELECT 
    c.customer_id,
    c.customer_name,
    COUNT(o.order_id) AS total_orders
FROM customer c
LEFT JOIN orders o ON c.customer_id = o.customer_id
GROUP BY c.customer_id, c.customer_name;
 


SELECT 
    s.order_id
FROM shipment s
WHERE s.warehouse_id = 3
  AND s.ship_date = '2009-11-01';





SELECT 
    w.warehouse_id,
    w.warehouse_city,
    s.order_id
FROM warehouse w
JOIN shipment s ON w.warehouse_id = s.warehouse_id;
