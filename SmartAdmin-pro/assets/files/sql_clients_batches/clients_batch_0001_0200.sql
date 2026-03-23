START TRANSACTION;
SET NAMES utf8mb4;

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Veronica Martins','Veromartins16@gmail.com','+35915814100','F','1981-09-16',NULL,'2022-02-23 19:31:00','2022-02-23 19:31:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Catarina Prata','catbprata@gmail.com','+351963160999',NULL,NULL,NULL,'2024-03-21 11:34:00','2024-03-21 11:34:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rosmery','import_batch_row3@example.com','+351920131488','F',NULL,NULL,'2024-03-25 10:46:00','2024-03-25 10:46:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rosa Mendes','rositamartins12@gmail.com','+351963768861','F','1949-11-12',NULL,'2024-03-25 16:08:00','2024-03-25 16:08:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Luciana Costa','import_batch_row5@example.com','+351914019106','F',NULL,NULL,'2024-03-25 16:28:00','2024-03-25 16:28:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Jhongreily Barboza','Jhonni_18@hotmail.com','+351939276081',NULL,NULL,NULL,'2024-03-25 19:37:00','2024-03-25 19:37:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Victoria Viera','import_batch_row7@example.com','+351912741259','F','2006-02-16',NULL,'2024-03-27 09:58:00','2024-03-27 09:58:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Paula Ramos','anapaularamos60@gmail.com','+351969565322','F',NULL,NULL,'2024-03-27 12:50:00','2024-03-27 12:50:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('teste teste','Teste@gmail.com','+351911111111',NULL,NULL,NULL,NOW(),NOW());
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Glaysy','import_batch_row10@example.com','+351928120695','F',NULL,NULL,'2024-04-03 09:03:00','2024-04-03 09:03:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lívia','import_batch_row11@example.com','+351915382883','F',NULL,NULL,'2024-04-03 09:06:00','2024-04-03 09:06:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Elisabete Mendes','import_batch_row12@example.com','+351919802056','F','1980-06-14',NULL,'2024-04-03 14:02:00','2024-04-03 14:02:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Hernani da Silva','import_batch_row13@example.com','+351969266565','M',NULL,NULL,'2024-04-17 15:02:00','2024-04-17 15:02:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Elisabete Dinis','import_batch_row14@example.com','+351962986463','M',NULL,NULL,'2024-04-18 16:39:00','2024-04-18 16:39:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Pamela','import_batch_row15@example.com','+351927148322','F',NULL,NULL,'2024-04-21 14:45:00','2024-04-21 14:45:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Anabela Nunes','import_batch_row16@example.com','+351966075170','F',NULL,NULL,'2024-04-22 10:13:00','2024-04-22 10:13:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Janeth Silva','abigailisamara@hotmail.com','+351911951877','F','1978-03-29','221050183','2024-04-22 10:36:00','2024-04-22 10:36:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Saudade Saraiva','import_batch_row18@example.com','+351910177870','F',NULL,NULL,'2024-04-22 14:53:00','2024-04-22 14:53:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('irina schlafrock','irina.schlafrock@gmail.com','+491701208878',NULL,NULL,NULL,'2024-04-27 10:19:00','2024-04-27 10:19:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rute Ataide','ruteatide@outlook.com','+351916953891',NULL,NULL,NULL,'2024-04-27 12:49:00','2024-04-27 12:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Fatima Silva','import_batch_row21@example.com','+351969266565','F',NULL,NULL,'2024-04-29 16:51:00','2024-04-29 16:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('THAINARA','import_batch_row22@example.com','+351927509894','F',NULL,NULL,'2024-04-30 09:38:00','2024-04-30 09:38:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Luadari Polanco','import_batch_row23@example.com','+351932818855','F','1993-11-29',NULL,'2024-04-30 13:38:00','2024-04-30 13:38:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ariyuri','ariyurireyes@gmail.com','+351928141170','F',NULL,NULL,'2024-05-01 17:40:00','2024-05-01 17:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Elis Souza','eli.a.souza@gmail.com','+351912018874',NULL,NULL,NULL,'2024-05-05 20:19:00','2024-05-05 20:19:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Amanda','import_batch_row26@example.com','+351936002103','F',NULL,NULL,'2024-05-05 20:50:00','2024-05-05 20:50:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Silva','import_batch_row27@example.com','+351917099861','F',NULL,NULL,'2024-05-07 10:15:00','2024-05-07 10:15:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Donatella','import_batch_row28@example.com','+351912665102','F',NULL,NULL,'2024-05-07 12:13:00','2024-05-07 12:13:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('MERICIEL','import_batch_row29@example.com','+351910680652','F','2024-05-14',NULL,'2024-05-08 14:18:00','2024-05-08 14:18:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('MAYANE MAYRINK YOO','import_batch_row30@example.com','+351924855140','F',NULL,NULL,'2024-05-09 14:37:00','2024-05-09 14:37:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('LUDO','import_batch_row31@example.com','+351917386856','F','1953-03-31',NULL,'2024-05-10 08:05:00','2024-05-10 08:05:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('MARIA PITARMA','import_batch_row32@example.com','+351915814100','F',NULL,NULL,'2024-05-10 09:48:00','2024-05-10 09:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('VALENTINA ANDLER','valeandler@gmail.com','+4917630644072','F','1997-06-02','325506701','2024-05-14 14:57:00','2024-05-14 14:57:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carolina Santos','carolina.ss@live.com.pt','+351961852356',NULL,NULL,NULL,'2022-10-19 17:11:00','2022-10-19 17:11:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Marlene Pimentel','import_batch_row35@example.com','+351912692805','F',NULL,NULL,'2024-05-21 09:37:00','2024-05-21 09:37:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ines Rocha','import_batch_row36@example.com','+351910574741','F',NULL,NULL,'2024-05-22 14:21:00','2024-05-22 14:21:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Salome Alves','import_batch_row37@example.com','+351916409551','F',NULL,NULL,'2024-05-23 09:37:00','2024-05-23 09:37:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Adriana Pinto','import_batch_row38@example.com','+351913056483','F',NULL,NULL,'2024-05-23 09:41:00','2024-05-23 09:41:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alejandra Lopez','import_batch_row39@example.com','+351917261774','M','1971-02-12',NULL,'2024-05-23 15:15:00','2024-05-23 15:15:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Aisling Vaughan','aislingvw@gmail.com','+353862073688',NULL,NULL,NULL,'2024-05-26 15:44:00','2024-05-26 15:44:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('luna alice','lunacurto6@gmail.com','+351910203361',NULL,NULL,NULL,'2024-05-28 14:34:00','2024-05-28 14:34:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Jessika Campos','import_batch_row42@example.com','+351932042447','F',NULL,NULL,'2024-05-28 18:21:00','2024-05-28 18:21:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lorena   Barros','import_batch_row43@example.com','+351912679832','F','1988-01-03',NULL,'2024-05-29 14:42:00','2024-05-29 14:42:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Fatima Mota','import_batch_row44@example.com','+351971526916','F',NULL,NULL,'2024-05-31 10:59:00','2024-05-31 10:59:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carla Gomes','carlacogomes@gmail.com','+351925664449','F','1983-10-31','238101150','2022-07-19 15:14:00','2022-07-19 15:14:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Laura Moratilla','laura.moratilla95@gmail.com','+34686773812',NULL,NULL,NULL,'2024-06-05 18:30:00','2024-06-05 18:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Angélica argentina','import_batch_row47@example.com','+351915510199','F',NULL,NULL,'2024-06-05 18:48:00','2024-06-05 18:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('claire','import_batch_row48@example.com','+16474021590','F',NULL,NULL,'2024-06-06 13:59:00','2024-06-06 13:59:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Lopes','dalma.store.aveiro@gmail.com','+351911550841','F',NULL,NULL,'2024-06-06 15:40:00','2024-06-06 15:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Camila Amarú','Amaruka92@gmail.com','+351919785610',NULL,NULL,NULL,'2024-06-07 09:16:00','2024-06-07 09:16:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lili','import_batch_row51@example.com','+351938808269','F',NULL,NULL,'2024-06-10 20:14:00','2024-06-10 20:14:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Bianca','import_batch_row52@example.com','+351910187598','F',NULL,NULL,'2024-06-10 20:16:00','2024-06-10 20:16:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Genesis Lara','laragenesisro@gmail.com','+351911046939','F',NULL,NULL,'2024-06-11 15:56:00','2024-06-11 15:56:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alice','import_batch_row54@example.com','+31653683788','F',NULL,NULL,'2024-06-11 16:39:00','2024-06-11 16:39:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Paula Barbosa','import_batch_row55@example.com','+351926797212','F',NULL,NULL,'2024-06-12 08:35:00','2024-06-12 08:35:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Almeida','anaclaudialmeida@outlook.pt','+351910928584',NULL,NULL,NULL,'2024-06-12 17:23:00','2024-06-12 17:23:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ynemar Trias','import_batch_row57@example.com','+351920172374','F',NULL,NULL,'2024-06-13 16:03:00','2024-06-13 16:03:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Eugenia Carvalho','import_batch_row58@example.com','+351914518796','F',NULL,NULL,'2024-06-13 16:10:00','2024-06-13 16:10:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ambar','import_batch_row59@example.com','+351915814100','F',NULL,NULL,'2024-06-15 11:15:00','2024-06-15 11:15:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Fernanda Capati','capati.fernanda@gmail.com','+351926489926','F',NULL,NULL,'2023-02-23 14:17:00','2023-02-23 14:17:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Caroline Fernandes','import_batch_row61@example.com','+351935976359','F',NULL,NULL,'2024-06-20 16:47:00','2024-06-20 16:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Thais Araujo','import_batch_row62@example.com','+351933161810','F',NULL,NULL,'2024-06-21 15:36:00','2024-06-21 15:36:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Julieta','import_batch_row63@example.com','+351915814100','F',NULL,NULL,'2024-06-22 13:36:00','2024-06-22 13:36:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carolina Quadro','import_batch_row64@example.com','+351926368836','F',NULL,NULL,'2024-06-24 14:24:00','2024-06-24 14:24:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('lyndy','import_batch_row65@example.com','+351928116419','F',NULL,NULL,'2024-06-24 15:33:00','2024-06-24 15:33:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mariana Carneiro Cult','import_batch_row66@example.com','+351963067821','F',NULL,NULL,'2024-06-28 09:54:00','2024-06-28 09:54:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria do Socorro Pessoa','import_batch_row67@example.com','+351910550714','F',NULL,NULL,'2024-07-01 09:35:00','2024-07-01 09:35:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Patricia Manuela','import_batch_row68@example.com','+351935226303','F',NULL,NULL,'2024-07-01 15:09:00','2024-07-01 15:09:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Andreia Pais','andreiapais2@hotmail.com','+351919120780',NULL,NULL,NULL,'2023-10-06 13:20:00','2023-10-06 13:20:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Luzimar Rosa','luzimar.rosas@gmail.com','+351933207177',NULL,NULL,NULL,'2024-07-04 17:03:00','2024-07-04 17:03:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('RITA OLIVEIRA','import_batch_row71@example.com','+351964712717','F',NULL,NULL,'2024-07-08 15:01:00','2024-07-08 15:01:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('mariana nicolau','import_batch_row72@example.com','+351916044826','F',NULL,NULL,'2024-07-10 16:52:00','2024-07-10 16:52:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Victoria Martins Lopes','vicml1108@gmail.com','+351919785610','F',NULL,NULL,'2024-07-11 13:12:00','2024-07-11 13:12:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Eliana spolador','import_batch_row74@example.com','+351910675592','F',NULL,NULL,'2024-07-11 17:57:00','2024-07-11 17:57:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Borges','import_batch_row75@example.com','+351913824868','F',NULL,NULL,'2024-07-13 16:43:00','2024-07-13 16:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Jose Pacheco','maria@mariapacheco.pt','+351966518825','F','2001-10-08',NULL,'2024-07-16 08:52:00','2024-07-16 08:52:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Thomas clarisse','thomasclarisse@yahoo.fr','+33622018277',NULL,NULL,NULL,'2024-07-16 17:48:00','2024-07-16 17:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Giha Nogueira','nogueiragihanat12@gmail.com','+351967461238','F','1991-06-12','263090442','2024-07-16 20:43:00','2024-07-16 20:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Bonny','import_batch_row79@example.com','+351927543394','F',NULL,NULL,'2024-07-17 11:06:00','2024-07-17 11:06:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Irene Gil','import_batch_row80@example.com','+34664394102','F',NULL,NULL,'2024-07-18 11:53:00','2024-07-18 11:53:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carina Filipa','carina.castanheta@gmail.com','+351910885803',NULL,NULL,NULL,'2024-07-20 09:43:00','2024-07-20 09:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carolina Nunes','carolinanunes.escola@gmail.com','+351913173822',NULL,NULL,NULL,'2023-07-10 10:25:00','2023-07-10 10:25:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Teresa Marques','teresasantos93@hotmail.com','+351965637399','F',NULL,'143959697','2024-07-25 11:49:00','2024-07-25 11:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Yanely da Silva','yanelydasilva@gmail.com','+351915328758','F',NULL,'240516427','2024-07-25 14:43:00','2024-07-25 14:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alexandra Torrealba','torrealbaconquista@gmail.com','+351914078820',NULL,NULL,NULL,'2022-06-16 19:39:00','2022-06-16 19:39:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alexia costa','alexiafacundo8@gmail.com','+351913470001',NULL,NULL,NULL,'2024-01-24 09:22:00','2024-01-24 09:22:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Bárbara Macedo','import_batch_row87@example.com','+351933896206','F',NULL,'208603522','2024-07-28 12:46:00','2024-07-28 12:46:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('João Paulo Fernandes','import_batch_row88@example.com','+4917682177524','M',NULL,NULL,'2024-07-29 15:23:00','2024-07-29 15:23:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Da Silva Elisabete','import_batch_row89@example.com','+351915814100','F',NULL,NULL,'2024-07-29 19:23:00','2024-07-29 19:23:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Cristina Guimarães','import_batch_row90@example.com','+351937250565','F',NULL,NULL,'2024-08-01 17:27:00','2024-08-01 17:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Natacha corte','import_batch_row91@example.com','+351916964081','F',NULL,NULL,'2024-08-02 21:39:00','2024-08-02 21:39:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('saramago sandrina','sandrine_saramago@yahoo.fr','+33760587291',NULL,NULL,NULL,'2024-08-09 15:02:00','2024-08-09 15:02:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('salome goncalves','salome.sgoncalves@gmail.com','+491786812366',NULL,NULL,NULL,'2024-08-10 15:03:00','2024-08-10 15:03:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ingrida','import_batch_row94@example.com','+351915814100','F',NULL,NULL,'2024-08-12 13:08:00','2024-08-12 13:08:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Laura','import_batch_row95@example.com','+351915814100','F',NULL,NULL,'2024-08-12 13:09:00','2024-08-12 13:09:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carolina Martins','carolllsilvaa12@gmail.com','+351915859021',NULL,NULL,NULL,'2024-08-12 13:48:00','2024-08-12 13:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Celeste Paiao','joaobelcardoso54@gmail.com','+351965281218','F',NULL,'164518282','2024-08-12 17:30:00','2024-08-12 17:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Laura Carlos','lauramartinscarlos@gmail.com','+351913138166','F',NULL,'264578317','2024-08-11 18:38:00','2024-08-11 18:38:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carolina Galeano','lcamarilla2021@hotmail.com','+351911128330','F',NULL,NULL,'2024-08-13 09:27:00','2024-08-13 09:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mariana Fanaia','import_batch_row100@example.com','+351913884442','F',NULL,NULL,'2024-08-13 13:04:00','2024-08-13 13:04:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mila Fanaia','import_batch_row101@example.com','+351911586535','F',NULL,NULL,'2024-08-13 13:08:00','2024-08-13 13:08:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Jacky','import_batch_row102@example.com','+61414956449','F',NULL,NULL,'2024-08-13 17:14:00','2024-08-13 17:14:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Julia barossi','import_batch_row103@example.com','+351936213091','F',NULL,NULL,'2024-08-14 14:30:00','2024-08-14 14:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('sandra lopez','import_batch_row104@example.com','+351915814100','F',NULL,NULL,'2024-08-14 18:33:00','2024-08-14 18:33:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Rita Pinho','import_batch_row105@example.com','+351915747896','F',NULL,NULL,'2024-08-16 16:47:00','2024-08-16 16:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Teles','miabteles@gmail.com','+41782562808',NULL,NULL,NULL,'2024-08-18 19:01:00','2024-08-18 19:01:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Melissa Sousa','sousa.mel@hotmail.com','+351913827823',NULL,NULL,'259132225','2022-01-04 16:59:00','2022-01-04 16:59:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alda Valado','alda.m.valado@gmail.com','+351915932030',NULL,NULL,NULL,'2024-08-19 16:19:00','2024-08-19 16:19:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Camila Lobo','import_batch_row109@example.com','+5521996469841','F',NULL,NULL,'2024-08-20 13:05:00','2024-08-20 13:05:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Debora Goncalves','deborafonseca98@gmail.com','+33613423338',NULL,NULL,NULL,'2023-11-25 14:37:00','2023-11-25 14:37:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Beatriz Marques','beatrizmmarques_sdc@hotmail.com','+351912646720',NULL,NULL,'271021993','2021-09-18 14:30:00','2021-09-18 14:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carlota Santos','carlota252004@gmail.com','+351969693396',NULL,NULL,NULL,'2022-12-27 10:45:00','2022-12-27 10:45:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Margarida Araújo','margaridachavesaraujo@gmail.com','+351915449650',NULL,NULL,NULL,'2023-10-17 09:00:00','2023-10-17 09:00:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sofia Perez','nagudelo50@gmail.com','+351934431365',NULL,NULL,NULL,'2024-08-28 11:40:00','2024-08-28 11:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rosario','import_batch_row115@example.com','+351969603360','M',NULL,NULL,'2024-08-28 17:51:00','2024-08-28 17:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sónia Ó','sonia.o@outlook.com','+351960319018',NULL,NULL,NULL,'2024-08-29 12:24:00','2024-08-29 12:24:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Natalia Rodrigues','import_batch_row117@example.com','+351934431365','F',NULL,NULL,'2024-08-31 12:53:00','2024-08-31 12:53:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sofia Perez Rodriguez','import_batch_row118@example.com','+14389244232','F',NULL,NULL,'2024-08-31 12:56:00','2024-08-31 12:56:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Cláudia Cerqueira','claudiamcerqueira@gmail.com','+351936401266',NULL,NULL,NULL,'2024-08-31 13:56:00','2024-08-31 13:56:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Denise','import_batch_row120@example.com','+351961071605','F',NULL,NULL,'2024-08-31 18:18:00','2024-08-31 18:18:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Matilde Almeida','matildealmeidacarlos@gmail.com','+351918723942','F',NULL,'265399467','2024-01-30 18:40:00','2024-01-30 18:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Veiga','import_batch_row122@example.com','+351967529048','F',NULL,NULL,'2024-09-03 16:24:00','2024-09-03 16:24:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Veda Gomes','veda.gomes20@gmail.com','+33663688657',NULL,NULL,NULL,'2024-09-09 11:43:00','2024-09-09 11:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Isis Carolini','import_batch_row124@example.com','+351939276825','F',NULL,NULL,'2024-09-09 14:01:00','2024-09-09 14:01:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('perola badra','Prelasdrudi123@gmail.com','+351933226201',NULL,NULL,NULL,'2023-03-09 17:04:00','2023-03-09 17:04:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Daniela Horta','import_batch_row126@example.com','+4522924014','F',NULL,NULL,'2024-09-11 16:09:00','2024-09-11 16:09:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mônica Rodrigues','monikapll_@hotmail.com','+351968141362',NULL,NULL,NULL,'2022-06-24 11:30:00','2022-06-24 11:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Cristiane Leiva','cristiane.leiva.silva@gmail.com','+351915091386',NULL,NULL,NULL,'2024-09-13 12:49:00','2024-09-13 12:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mericiel Dominges','mericiel@hotmail.com','+351912479900',NULL,NULL,NULL,'2024-09-15 23:28:00','2024-09-15 23:28:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mari Rodrigues','import_batch_row130@example.com','+351938024558','F',NULL,NULL,'2024-09-16 10:29:00','2024-09-16 10:29:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carolina Rodrigues','import_batch_row131@example.com','+351932747256','F',NULL,NULL,'2024-09-16 11:44:00','2024-09-16 11:44:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Soraia Mendonça','import_batch_row132@example.com','+351966999179','F',NULL,NULL,'2024-09-17 13:25:00','2024-09-17 13:25:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sandra Vieira','sav.vieira@gmail.com','+351919081927','F',NULL,NULL,'2021-10-12 13:43:00','2021-10-12 13:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Natacha Alvarez','nats605@hotmail.com','+351918284401',NULL,NULL,NULL,'2022-03-21 09:49:00','2022-03-21 09:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Manuel Candal','import_batch_row135@example.com','+351919765890','F',NULL,NULL,'2024-09-24 09:58:00','2024-09-24 09:58:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sonia Almeida','soniamarisalmeida@gmail.com','+351968905247','F',NULL,'222101466','2024-10-01 14:40:00','2024-10-01 14:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Costa','anaconda1104@gmail.com','+351917540039','F',NULL,'153125209','2024-10-01 16:33:00','2024-10-01 16:33:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sofia Fernandes','sofiafernandes1029@gmail.com','+351939253693','F',NULL,'273464108','2024-10-04 11:58:00','2024-10-04 11:58:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('matilde lourenço','Matildelourenco105@Gmail.com','+351935559132',NULL,NULL,NULL,'2024-10-04 15:42:00','2024-10-04 15:42:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Denise Silva','import_batch_row140@example.com','+351931423773',NULL,NULL,NULL,'2024-10-04 16:42:00','2024-10-04 16:42:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alessandra Franzoni','alessandraafranzoni@gmail.com','+393454422599',NULL,NULL,NULL,'2024-10-04 16:55:00','2024-10-04 16:55:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vera Paleothodorou','vera.paleothodorou@gmail.com','+306949166909',NULL,NULL,NULL,'2024-10-05 16:03:00','2024-10-05 16:03:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('penelope benz','Benzpenny@yahoo.com','+18172355917',NULL,NULL,NULL,'2024-10-08 13:28:00','2024-10-08 13:28:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Joana Vieira','import_batch_row144@example.com','+351913380332','F',NULL,NULL,'2024-10-09 11:00:00','2024-10-09 11:00:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Claudia de Jesus','import_batch_row145@example.com','+351912155502','F',NULL,NULL,'2024-10-09 16:41:00','2024-10-09 16:41:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Salete','import_batch_row146@example.com','+351912675311','F',NULL,NULL,'2024-10-10 08:55:00','2024-10-10 08:55:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Joana Gonçalves','joanaclg98@gmail.com','+351915456544',NULL,NULL,NULL,'2024-10-10 16:17:00','2024-10-10 16:17:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('karla pas','Karlapas@regra3social.com','+351966385723',NULL,NULL,NULL,'2024-10-14 17:48:00','2024-10-14 17:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Gabi Desmolins','import_batch_row149@example.com','+351932016542','F',NULL,NULL,'2024-10-15 12:41:00','2024-10-15 12:41:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Olga fonseca','olgafonseca13@gmail.com','+351966756788','F',NULL,NULL,'2024-10-17 17:58:00','2024-10-17 17:58:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Paula Campos','import_batch_row151@example.com','+447482626278','F',NULL,NULL,'2024-10-18 11:18:00','2024-10-18 11:18:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Melissa Kellen','melissambs1197@gmail.com','+351932051753','F',NULL,NULL,'2024-10-14 14:29:00','2024-10-14 14:29:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Jordao Alvez','import_batch_row153@example.com','+351932051753','M',NULL,NULL,'2024-10-21 13:55:00','2024-10-21 13:55:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Naira Coutinho','nairacoutinho645@gmail.com','+351939020843',NULL,NULL,NULL,'2024-10-21 18:16:00','2024-10-21 18:16:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Inês Almeida','inesalmeida030206@gmail.com','+351961014769',NULL,NULL,'258760400','2023-01-28 11:25:00','2023-01-28 11:25:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Beatriz Rodrigues','import_batch_row156@example.com','+351916402346','F',NULL,NULL,'2024-10-24 08:17:00','2024-10-24 08:17:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vanessa Barbosa','wannessa_b@hotmail.com','+351936435564',NULL,NULL,NULL,'2024-10-25 17:00:00','2024-10-25 17:00:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Margarida','margaridarocha2002@gmail.com','+351962599498',NULL,NULL,NULL,'2024-10-16 14:11:00','2024-10-16 14:11:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Beatriz Tavares','import_batch_row159@example.com','+351926158413','F',NULL,NULL,'2024-10-30 15:24:00','2024-10-30 15:24:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Tiago','import_batch_row160@example.com','+351921459591','M',NULL,NULL,'2024-10-31 10:08:00','2024-10-31 10:08:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Simone Silva','import_batch_row161@example.com','+351935131493','F',NULL,NULL,'2024-10-31 10:10:00','2024-10-31 10:10:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Marcia camargo','import_batch_row162@example.com','+351963462014','F',NULL,NULL,'2024-10-31 17:23:00','2024-10-31 17:23:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Eduarda Cavalcante','import_batch_row163@example.com','+351934198150','F',NULL,NULL,'2024-11-06 10:43:00','2024-11-06 10:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Thainara','import_batch_row164@example.com','+351927509894','F',NULL,NULL,'2024-11-06 10:47:00','2024-11-06 10:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vitoria Martins','vitoria.hmq@gmail.com','+351925626400',NULL,NULL,NULL,'2024-11-06 13:05:00','2024-11-06 13:05:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Cleisiomara','marasalo18@gmail.com','+351926360084','F',NULL,NULL,'2024-11-06 14:31:00','2024-11-06 14:31:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Gabriela Rebelo','gabrielarebelo@gmail.com','+351911963314',NULL,NULL,NULL,'2024-11-06 21:27:00','2024-11-06 21:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Candida Pietrosemoli','candidapietrosemoli@gmail.com','+491719274529',NULL,NULL,NULL,'2024-11-07 17:32:00','2024-11-07 17:32:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Wendy Mattos','import_batch_row169@example.com','+351933541706','F',NULL,NULL,'2024-11-08 10:57:00','2024-11-08 10:57:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Solange Lima','solangemarinalima@hotmail.com','+351911887301',NULL,NULL,NULL,'2023-10-26 07:55:00','2023-10-26 07:55:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alisa Gudovskaja','alisa.gudovskaja@gmail.com','+37258440065',NULL,NULL,NULL,'2024-11-14 11:54:00','2024-11-14 11:54:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rosa miguel','import_batch_row172@example.com','+351915814100','F',NULL,NULL,'2024-11-14 15:40:00','2024-11-14 15:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('zeloi','import_batch_row173@example.com','+351927143977','F',NULL,NULL,'2024-11-14 16:40:00','2024-11-14 16:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('julia','import_batch_row174@example.com','+33695350653','F',NULL,NULL,'2024-11-15 16:27:00','2024-11-15 16:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Filipa Amorim','filipasousaamorim@gmail.com','+351914368508',NULL,NULL,NULL,'2024-11-16 22:29:00','2024-11-16 22:29:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Menezes','ana.mnzs@gmail.com','+351919151179','F','1984-11-30',NULL,'2024-11-18 14:01:00','2024-11-18 14:01:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('kamylle marques','marquesskamyy@gmail.com','+351935009096',NULL,NULL,NULL,'2024-11-18 17:30:00','2024-11-18 17:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Miriam Gama','import_batch_row178@example.com','+351912499972','F',NULL,NULL,'2024-11-18 22:07:00','2024-11-18 22:07:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Laura Beer','import_batch_row179@example.com','+351913365169','F',NULL,NULL,'2024-11-19 14:34:00','2024-11-19 14:34:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vera Nogueira','veranogueira1078@gmail.com','+351933210973','F',NULL,NULL,'2022-10-10 18:04:00','2022-10-10 18:04:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Frankling Barros','Franklin.bbaros@gmail.com','+351933065428','M',NULL,NULL,'2024-11-20 10:58:00','2024-11-20 10:58:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Bruna Marcondes','bru.temp17@gmail.com','+351910486917','F','1996-11-26',NULL,'2024-11-21 09:40:00','2024-11-21 09:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Elis Yoo','import_batch_row183@example.com','+351912018874','F',NULL,NULL,'2024-11-22 19:22:00','2024-11-22 19:22:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Maria Figueira','import_batch_row184@example.com','+351913259485','F',NULL,NULL,'2024-11-23 10:53:00','2024-11-23 10:53:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Paula Peres','import_batch_row185@example.com','+351927422539','F',NULL,NULL,'2024-11-26 13:12:00','2024-11-26 13:12:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('SOFIJA BORKOVIC','sofijaborkovic@gmail.com','+381693089863',NULL,NULL,NULL,'2024-11-26 14:48:00','2024-11-26 14:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('mariana Bom','import_batch_row187@example.com','+351927543160','F',NULL,NULL,'2024-11-26 16:05:00','2024-11-26 16:05:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('sofia pinho','Sofiapinho1808@gmail.com','+351930613464',NULL,NULL,NULL,'2024-11-27 11:29:00','2024-11-27 11:29:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Cirlene','import_batch_row189@example.com','+351912503408','F',NULL,NULL,'2024-11-28 09:36:00','2024-11-28 09:36:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Helena Costa','Laurassbeer@hotmail.com','+351914304142',NULL,NULL,NULL,'2024-11-28 18:23:00','2024-11-28 18:23:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sepideh Sharbaf','sepideh.sharbaf@gmail.com','+31620327867',NULL,NULL,NULL,'2024-11-29 09:43:00','2024-11-29 09:43:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sofia Godinho','sofsgodinho27@gmail.com','+351916593619','F','1998-01-08','229389520','2022-11-12 11:08:00','2022-11-12 11:08:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('mel brait','Mel.brait@hotmail.com','+5511975663140',NULL,NULL,NULL,'2024-12-02 10:13:00','2024-12-02 10:13:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Illa Samille','import_batch_row194@example.com','+5575988488747','F',NULL,NULL,'2024-12-02 11:21:00','2024-12-02 11:21:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Fernanda Rodrigues','import_batch_row195@example.com','+351913289275','F',NULL,NULL,'2024-12-02 13:54:00','2024-12-02 13:54:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Simone Silva','import_batch_row196@example.com','+351935131493','F',NULL,NULL,'2024-12-02 19:31:00','2024-12-02 19:31:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Joao','import_batch_row197@example.com','+351910696177','M',NULL,NULL,'2024-12-02 19:37:00','2024-12-02 19:37:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Santos','import_batch_row198@example.com','+351917069909','F',NULL,NULL,'2024-12-03 10:47:00','2024-12-03 10:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('carolina marinho','import_batch_row199@example.com','+5521979859026','M',NULL,NULL,'2024-12-03 14:11:00','2024-12-03 14:11:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Celestino da','costajack316@gmail.com','+351935323344',NULL,NULL,NULL,'2024-12-04 09:21:00','2024-12-04 09:21:00');
SET @client_id := LAST_INSERT_ID();

COMMIT;
