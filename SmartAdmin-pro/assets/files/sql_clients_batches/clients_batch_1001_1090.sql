START TRANSACTION;
SET NAMES utf8mb4;

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Correia','maria.s.correia2008@gmail.com','+351926943796','F','2008-05-29',NULL,'2026-02-12 09:02:00','2026-02-12 09:02:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Heloisa Aquino','heloisa.aquinohsa@hotmail.com','+351960344635',NULL,NULL,NULL,'2025-04-08 08:27:00','2025-04-08 08:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Isabelle Valente','isabellevalentec@gmail.com','+351932353986','F','2000-09-25','313446385','2026-02-12 10:31:00','2026-02-12 10:31:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana  Paula Lima','import_batch_row1004@example.com','+351937640956','F',NULL,NULL,'2026-02-12 10:49:00','2026-02-12 10:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Matilde Vieira','import_batch_row1005@example.com','+351933055585','F',NULL,NULL,'2026-02-12 13:52:00','2026-02-12 13:52:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Edna Freitas','ednairenemanuel@gmail.com','+351932198474',NULL,NULL,NULL,'2026-02-12 16:13:00','2026-02-12 16:13:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Irene Oliveira','oliveiranovaesirene@gmail.com','+351929410675','F','1996-07-25','334835232','2026-02-12 21:40:00','2026-02-12 21:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('anabela marques','fabiano.anabela0000@gmail.com','+351923185190',NULL,NULL,NULL,'2026-02-12 21:48:00','2026-02-12 21:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sara Figueiredo','import_batch_row1009@example.com','+351964120965','F',NULL,NULL,'2026-02-14 10:35:00','2026-02-14 10:35:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Catarina Rodrigues','catarinasofiafr@gmail.com','+351916540387',NULL,NULL,NULL,'2025-12-03 01:00:00','2025-12-03 01:00:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Dunja','import_batch_row1011@example.com',NULL,'M',NULL,NULL,'2026-02-15 14:22:00','2026-02-15 14:22:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Nuria Fernandez','nuria.fdez99@gmail.com','+34638665275','F','1999-04-16',NULL,'2026-02-15 18:44:00','2026-02-15 18:44:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Paula Vilali','paulavilali5@gmail.com','+351928290519',NULL,NULL,NULL,'2026-02-17 20:02:00','2026-02-17 20:02:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Liliana Paulo','borboleta21lili@gmail.com','+41767437867',NULL,NULL,NULL,'2026-02-18 17:23:00','2026-02-18 17:23:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Andreia Santos','import_batch_row1015@example.com','+351938209556',NULL,NULL,NULL,'2026-02-19 11:04:00','2026-02-19 11:04:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lucia Serrano','import_batch_row1016@example.com','+34685261910','F',NULL,NULL,'2026-02-19 11:47:00','2026-02-19 11:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vitoria Queiroz','vm_queiroz@hotmail.com','+351966355289',NULL,NULL,NULL,'2026-02-19 17:07:00','2026-02-19 17:07:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lucia Naia','lucia.naia@hotmail.com','+351914207951','F','1964-08-06','189996196','2026-02-19 17:54:00','2026-02-19 17:54:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Manuela Alvez','manuela.alves2009@live.com.pt','+351914601296','F',NULL,NULL,'2021-12-21 21:27:00','2021-12-21 21:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Diana Isabel Pereira Bento','dianabento56@gmail.com','+351917404436','F','2006-11-21',NULL,'2025-01-30 10:57:00','2025-01-30 10:57:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('beatriz matos','beatrizmmdematos@gmail.com','+351933870876','F','1995-01-27','253606608','2021-10-26 20:32:00','2021-10-26 20:32:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Nathalya','Nathalya1@outlook.pt','+351934141341','F',NULL,NULL,'2023-11-20 12:34:00','2023-11-20 12:34:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Giulianna Thibes','giuliannathibes10@gmail.com','+351927972007','F','2000-10-22','324201788','2026-02-20 20:30:00','2026-02-20 20:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vanessa Tanko','Vanessatanko9@gmail.com','+351961133386',NULL,NULL,NULL,'2026-02-21 01:58:00','2026-02-21 01:58:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Beatriz Barbosa','beatrizabarbosa08@gmail.com','+351939430612','F','2006-11-08',NULL,'2024-07-09 12:34:00','2024-07-09 12:34:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Odair Fabio','import_batch_row1026@example.com','+351936429839','M',NULL,NULL,'2026-02-21 15:56:00','2026-02-21 15:56:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Yesika Agrela','Yesikagrela@hotmail.com','+35197023815258',NULL,NULL,NULL,'2026-02-22 12:16:00','2026-02-22 12:16:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Anna Maris','Aartmaris@gmail.com','+31613606275',NULL,NULL,NULL,'2026-02-22 12:45:00','2026-02-22 12:45:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Julia Hoff','jm.hoff@student.avans.nl','+31639037266',NULL,NULL,NULL,'2026-02-22 12:51:00','2026-02-22 12:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maya Marques','mayasantosmarques@gmail.com','+35192525193','F','2008-06-24',NULL,'2024-05-03 18:24:00','2024-05-03 18:24:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Guiselle llavali','import_batch_row1031@example.com','+351915628361','F',NULL,NULL,'2026-02-23 12:20:00','2026-02-23 12:20:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Eliana Ferreira','Elianaferreira925@gmail.com','+33661023405',NULL,NULL,NULL,'2026-02-23 12:35:00','2026-02-23 12:35:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Saphi Baechle','import_batch_row1033@example.com','+4367762190285','F',NULL,NULL,'2026-02-23 14:13:00','2026-02-23 14:13:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('sofia vas','sofiavas@sapo.pt','+351914039164','F',NULL,'219595720','2026-02-23 14:40:00','2026-02-23 14:40:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Godeliva Hanjam','hgodeliva@gmail.com','+351930504423','F','1998-05-19',NULL,'2024-01-30 11:11:00','2024-01-30 11:11:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Livia Rincon','Soyliviarincon@gmail.com','+351968019118','M',NULL,NULL,'2025-03-11 16:20:00','2025-03-11 16:20:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Katia Mauricio','kmauricio13@gmail.com','+351912188410','F','1992-04-13',NULL,'2022-06-27 15:27:00','2022-06-27 15:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ines santos matos','import_batch_row1038@example.com','+351963697728','F',NULL,NULL,'2026-02-24 18:10:00','2026-02-24 18:10:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Filipa Tavares','filipatavares1316@gmail.com','+351913346224','F','2002-04-24',NULL,'2026-02-24 23:31:00','2026-02-24 23:31:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rita Espindola','rita2011espindola@gmail.com','+351912406770','F','2001-07-04','249012715','2025-12-02 21:32:00','2025-12-02 21:32:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('stephanie de','Stephanie.de.pelsmaeker@gmail.com','+351912909351',NULL,NULL,NULL,'2024-07-08 22:07:00','2024-07-08 22:07:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lara Sousa','larabalieiro7@gmail.com','+351937020988',NULL,NULL,NULL,'2025-08-10 13:48:00','2025-08-10 13:48:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ana Neves','aritadasneves@gmail.com','+351918613756',NULL,NULL,NULL,'2025-11-10 13:55:00','2025-11-10 13:55:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('melissa suarez','import_batch_row1044@example.com','+351929406261','F',NULL,NULL,'2026-02-27 13:51:00','2026-02-27 13:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alessia Chirico','alessiadpg777@gmail.com','+393663027101',NULL,NULL,NULL,'2026-02-27 13:51:00','2026-02-27 13:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Sara Anzalone','sara.anzalone894@gmail.com',NULL,NULL,NULL,NULL,'2026-02-27 13:51:00','2026-02-27 13:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Angela Gaspar','angelagaspar2000@gmail.com','+351912374014','F',NULL,'261468634','2026-02-28 15:09:00','2026-02-28 15:09:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lena Polyvyannaya','Lpoly@hotmail.com','+351922002905',NULL,NULL,NULL,'2026-02-28 19:59:00','2026-02-28 19:59:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Maria Portugal','Mjpdias23@gmail.com','+351918629983',NULL,NULL,NULL,'2026-03-01 16:21:00','2026-03-01 16:21:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Silvia Nunes','Silvia.ferreira.illertissen@gmail.com','+4915237205212',NULL,NULL,NULL,'2026-03-01 20:30:00','2026-03-01 20:30:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Lara Capela','laralocapela@gmail.com','+351964470156',NULL,NULL,NULL,'2026-03-01 21:03:00','2026-03-01 21:03:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Júlia Sant?anna','juliasantanna084@gmail.com','+351913128826',NULL,NULL,'334141575','2026-03-02 09:51:00','2026-03-02 09:51:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('ana mesquita','caroline.buenomesquita@gmail.com','+351916002577',NULL,NULL,'328010650','2025-04-01 13:11:00','2025-04-01 13:11:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('catarina antunes','import_batch_row1054@example.com','+351926703624','M',NULL,NULL,'2026-03-02 13:50:00','2026-03-02 13:50:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Leonor Santos pereira','import_batch_row1055@example.com','+351910534270','F',NULL,NULL,'2026-03-02 14:14:00','2026-03-02 14:14:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Alana Lopes','import_batch_row1056@example.com','+351932188655','F',NULL,NULL,'2026-03-02 15:07:00','2026-03-02 15:07:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Karine Mello','Kmello684@gmail.com','+351961875743','F','2001-10-18',NULL,'2026-03-02 20:12:00','2026-03-02 20:12:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('zahra harabi','Zazaharabiza@gmail.com','+447549295282',NULL,NULL,NULL,'2025-07-12 00:49:00','2025-07-12 00:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Jai Morris','import_batch_row1059@example.com','+13018141391','F',NULL,NULL,'2026-03-04 13:47:00','2026-03-04 13:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Vânia Rocha','Vanis_rocha@hotmail.com','+351917093102',NULL,NULL,NULL,'2025-03-02 22:47:00','2025-03-02 22:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Catarina Piskhchyk','catarina200301@gmail.com','+351936778207','F','2003-01-12',NULL,'2026-03-05 12:15:00','2026-03-05 12:15:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Marlene Saria','marlene.saria@icloud.com','+436644006725',NULL,NULL,NULL,'2026-03-05 12:52:00','2026-03-05 12:52:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('catarina Maçarico','catarina.macarico@gmail.com','+351918613627','F','1999-03-26','258521708','2024-11-26 11:12:00','2024-11-26 11:12:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Melissa Vélez','meli09661@gmail.com','+351911880633',NULL,NULL,NULL,'2026-03-06 15:39:00','2026-03-06 15:39:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Fátima Ferrer','mfatimaferrer@gmail.com','+351933823974',NULL,NULL,NULL,'2026-03-08 15:20:00','2026-03-08 15:20:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mariana Bernardo','marianarmbernardo@gmail.com','+351912817645',NULL,NULL,NULL,'2026-03-09 14:49:00','2026-03-09 14:49:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Justina Karciauskaite','import_batch_row1067@example.com','+37061248505','F',NULL,NULL,'2026-03-09 15:00:00','2026-03-09 15:00:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mariela Andrade','import_batch_row1068@example.com','+351937189346','F',NULL,NULL,'2026-03-09 18:59:00','2026-03-09 18:59:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Joana Amaral','joanaamaral81@gmail.com','+351915213666',NULL,NULL,NULL,'2026-03-09 22:24:00','2026-03-09 22:24:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Andrieli Batista da silva','import_batch_row1070@example.com','+351910194137','F',NULL,NULL,'2026-03-10 16:25:00','2026-03-10 16:25:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ronisia bom','ronisiaceita4@gmail.com','+351925919083',NULL,NULL,NULL,'2026-03-10 18:33:00','2026-03-10 18:33:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Solange Silva','Solangewassolua.ss@gmail.com','+351931479335',NULL,NULL,NULL,'2026-03-10 20:06:00','2026-03-10 20:06:00');
SET @client_id := LAST_INSERT_ID();
INSERT INTO notes (notable_type,notable_id,user_id,type,note,reminder_at,reminder_advance_minutes,reminder_sent,created_at,updated_at) VALUES ('App\\Models\\Client',@client_id,NULL,'geral','não foi atendida por presentar um fungo  grave con unha partida no dedo pulgar da mão esquerda',NULL,15,0,'2026-03-10 20:06:00','2026-03-10 20:06:00');

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Carlos Granado','import_batch_row1073@example.com','+351923120791','M',NULL,NULL,'2026-03-11 10:27:00','2026-03-11 10:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Bruna Merendeiro','merendeirobuna@gmail.com','+351910407322',NULL,NULL,NULL,'2026-03-11 12:33:00','2026-03-11 12:33:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Camile Veloso','camilemayara94@gmail.com','+351910230470',NULL,NULL,NULL,'2022-08-22 13:15:00','2022-08-22 13:15:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Mariana Sacchetti','marianasac8@gmail.com','+351966492153',NULL,NULL,NULL,'2025-10-29 17:19:00','2025-10-29 17:19:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Luciana','import_batch_row1077@example.com','+351910287247','F',NULL,NULL,'2026-03-14 12:07:00','2026-03-14 12:07:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Angeles Ascanio','AngelesVeS07@gmail.com','+351931186965',NULL,NULL,NULL,'2026-03-14 12:47:00','2026-03-14 12:47:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Amelie Seidel','ame.seidel@googlemail.com','+4915730188155',NULL,NULL,NULL,'2026-03-14 13:46:00','2026-03-14 13:46:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Patricia Soares','psnpatriciasnascimento@gmail.com','+351921466332',NULL,NULL,NULL,'2026-03-15 11:36:00','2026-03-15 11:36:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Hany Dos Santos','hanyfotografia@gmail.com','+351928279293','F','1998-12-09',NULL,'2026-03-16 11:27:00','2026-03-16 11:27:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Hadassa batista','import_batch_row1082@example.com','+351932331131','F',NULL,NULL,'2026-03-16 17:06:00','2026-03-16 17:06:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Regina Goossen','regina.goossen@gmail.com','+31623095316',NULL,NULL,NULL,'2026-03-16 22:33:00','2026-03-16 22:33:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Rita Pereira','ritapqtt@gmail.com','+351913747293',NULL,NULL,NULL,'2023-07-05 12:15:00','2023-07-05 12:15:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Ingrid','import_batch_row1085@example.com',NULL,'M',NULL,NULL,'2026-03-17 13:38:00','2026-03-17 13:38:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Leticia Silva','leticia.andreia.silva6@gmail.com','+351916038561',NULL,NULL,'224332112','2020-11-24 15:29:00','2020-11-24 15:29:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Tuba Göç','goctuba@gmail.com','+351935034034',NULL,NULL,NULL,'2026-03-18 16:00:00','2026-03-18 16:00:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Inês Silva','inescordeirosilva7@gmail.com','+351924051130',NULL,NULL,NULL,'2025-06-22 12:35:00','2025-06-22 12:35:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Celeste Almeida','celestea120@gmail.com','+351915710488',NULL,NULL,'243928777','2025-10-16 10:25:00','2025-10-16 10:25:00');
SET @client_id := LAST_INSERT_ID();

INSERT INTO clients (name,email,phone,gender,birth_date,nif,created_at,updated_at) VALUES ('Clara Pinto','import_batch_row1090@example.com','+351961354373','F',NULL,NULL,'2026-03-19 16:37:00','2026-03-19 16:37:00');
SET @client_id := LAST_INSERT_ID();

COMMIT;
