START TRANSACTION;
SET NAMES utf8mb4;

-- Row 0
INSERT INTO calendar_events (title,start_at,end_at,description,user_id,client_id,service_id,event_type,status,created_at,updated_at)
VALUES (
  NULL,
  NULL,
  DATE_ADD(NULL, INTERVAL (SELECT COALESCE(SUM(s.duration),0) FROM services s WHERE s.name IN (NULL)) MINUTE),
  NULL,
  COALESCE(
  (SELECT u.id FROM users u WHERE u.id =  AND u.role = 'tecnico' LIMIT 1),
  (SELECT a.user_id FROM agents a WHERE a.id =  LIMIT 1)
),
  NULL,
  (SELECT s.id FROM services s WHERE s.name = NULL LIMIT 1),
  'marcacao',
  NULL,
  NOW(), NOW()
);
SET @event_id := LAST_INSERT_ID();

COMMIT;
