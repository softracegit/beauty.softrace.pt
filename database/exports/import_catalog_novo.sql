-- =============================================================================
-- Importação de catálogo (categorias + serviços + variantes + extras + ligações)
-- Gerado a partir da BD local — SEM técnicas / agent_service / agent_service_extra
--
-- Regras aplicadas:
--   * As 4 categorias ficam hidden_from_booking = 1
--   * Serviços "Remoção" e "Reparação" ficam hidden_from_booking = 1
--   * Restantes serviços ficam hidden_from_booking = 0
--
-- ANTES DE CORRER: define @organization_id (e opcionalmente @store_id).
-- =============================================================================

SET NAMES utf8mb4;
SET @organization_id := 1;  -- << ALTERA para o organization_id do servidor
SET @store_id := NULL;     -- normalmente NULL (catálogo ao nível da organização)
SET @now := '2026-11-24 20:07:23';

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1) Categorias de serviço (todas ocultas no booking)
-- ---------------------------------------------------------------------------
INSERT INTO `categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  'Manicure NOVO',
  NULL,
  '#99f6e4',
  1,
  1, -- oculta
  @now,
  @now,
  NULL
);
SET @cat_11 := LAST_INSERT_ID();

INSERT INTO `categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  'Pedicure NOVO',
  NULL,
  '#99f6e4',
  2,
  1, -- oculta
  @now,
  @now,
  NULL
);
SET @cat_12 := LAST_INSERT_ID();

INSERT INTO `categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  'Pestanas NOVO',
  NULL,
  '#99f6e4',
  3,
  1, -- oculta
  @now,
  @now,
  NULL
);
SET @cat_13 := LAST_INSERT_ID();

INSERT INTO `categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  'Rosto NOVO',
  NULL,
  '#99f6e4',
  4,
  1, -- oculta
  @now,
  @now,
  NULL
);
SET @cat_14 := LAST_INSERT_ID();

-- ---------------------------------------------------------------------------
-- 2) Categorias de extras
-- ---------------------------------------------------------------------------
INSERT INTO `extra_categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `created_at`, `updated_at`) VALUES (
  @organization_id,
  @store_id,
  'Nail Art',
  NULL,
  '#fda4af',
  1,
  @now,
  @now
);
SET @ecat_2 := LAST_INSERT_ID();

INSERT INTO `extra_categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `created_at`, `updated_at`) VALUES (
  @organization_id,
  @store_id,
  'Pestanas',
  NULL,
  '#3498db',
  2,
  @now,
  @now
);
SET @ecat_3 := LAST_INSERT_ID();

INSERT INTO `extra_categories` (`organization_id`, `store_id`, `name`, `description`, `color`, `sort_order`, `created_at`, `updated_at`) VALUES (
  @organization_id,
  @store_id,
  'Acabamentos e efeitos',
  NULL,
  '#e9d5ff',
  3,
  @now,
  @now
);
SET @ecat_4 := LAST_INSERT_ID();

-- ---------------------------------------------------------------------------
-- 3) Extras
-- ---------------------------------------------------------------------------
INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_2,
  'Nail Art Básico',
  'Frase curta sobre este serviço Frase curta sobre este serviço Frase curta sobre este serviço',
  'Frase curta sobre este serviço',
  3.00,
  20,
  1,
  @now,
  @now,
  NULL
);
SET @extra_7 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_2,
  'Nail Art Avançado',
  NULL,
  NULL,
  5.00,
  20,
  2,
  @now,
  @now,
  NULL
);
SET @extra_8 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_2,
  'Nail Art Personalizado',
  NULL,
  NULL,
  0.00,
  30,
  3,
  @now,
  @now,
  NULL
);
SET @extra_9 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_3,
  'Efeito Molhado',
  NULL,
  NULL,
  0.00,
  0,
  1,
  @now,
  @now,
  NULL
);
SET @extra_13 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_3,
  'Efeito Fox',
  NULL,
  NULL,
  0.00,
  0,
  2,
  @now,
  @now,
  NULL
);
SET @extra_14 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_3,
  'Efeito Wispy',
  NULL,
  NULL,
  0.00,
  0,
  3,
  @now,
  @now,
  NULL
);
SET @extra_15 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_3,
  'Efeito Kim Kardashian',
  NULL,
  NULL,
  0.00,
  0,
  4,
  @now,
  @now,
  NULL
);
SET @extra_16 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_3,
  'Efeito Anime',
  NULL,
  NULL,
  0.00,
  0,
  5,
  @now,
  @now,
  NULL
);
SET @extra_17 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_4,
  'Francesinha',
  NULL,
  NULL,
  4.00,
  15,
  4,
  @now,
  @now,
  NULL
);
SET @extra_10 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_4,
  'Olho de Gato',
  NULL,
  NULL,
  4.00,
  20,
  5,
  @now,
  @now,
  NULL
);
SET @extra_11 := LAST_INSERT_ID();

INSERT INTO `extras` (`extra_category_id`, `name`, `description`, `short_description`, `price`, `duration`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @ecat_4,
  'Baby Color Boomer',
  NULL,
  NULL,
  3.00,
  15,
  6,
  @now,
  @now,
  NULL
);
SET @extra_12 := LAST_INSERT_ID();

-- ---------------------------------------------------------------------------
-- 4) Serviços (+ variantes)
--     Remoção / Reparação: hidden=1; restantes: hidden=0
-- ---------------------------------------------------------------------------
-- Serviço: Tradicional
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_11,
  'Tradicional',
  NULL,
  NULL,
  45,
  15.00,
  15.00,
  1,
  0,
  @now,
  @now,
  NULL
);
SET @svc_961 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_961,
  'Tradicional',
  NULL,
  NULL,
  45,
  15.00,
  15.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_961,
  'Tradicional Masculina',
  NULL,
  NULL,
  45,
  15.00,
  15.00,
  1,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Verniz Gel
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_11,
  'Verniz Gel',
  NULL,
  NULL,
  60,
  20.00,
  20.00,
  2,
  0,
  @now,
  @now,
  NULL
);
SET @svc_962 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_962,
  'Verniz Gel / Gelinho',
  NULL,
  NULL,
  60,
  20.00,
  20.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_962,
  'Reparação',
  NULL,
  NULL,
  10,
  3.00,
  3.00,
  1,
  0,
  'addon',
  @now,
  @now
);

-- Serviço: Unhas de Gel
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_11,
  'Unhas de Gel',
  NULL,
  NULL,
  70,
  24.00,
  24.00,
  3,
  0,
  @now,
  @now,
  NULL
);
SET @svc_963 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_963,
  'Sobre unha natural',
  NULL,
  NULL,
  70,
  24.00,
  24.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_963,
  'Com extensão',
  NULL,
  NULL,
  110,
  35.00,
  35.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_963,
  'Manutenção Cliente Fada',
  NULL,
  NULL,
  90,
  27.00,
  27.00,
  2,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_963,
  'Manutenção de profissional externa',
  NULL,
  NULL,
  110,
  30.00,
  30.00,
  3,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_963,
  'Reparação',
  NULL,
  NULL,
  15,
  4.00,
  4.00,
  4,
  0,
  'addon',
  @now,
  @now
);

-- Serviço: Unhas de Acrílico
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_11,
  'Unhas de Acrílico',
  NULL,
  NULL,
  70,
  27.00,
  27.00,
  4,
  0,
  @now,
  @now,
  NULL
);
SET @svc_964 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_964,
  'Sobre unha natural',
  NULL,
  NULL,
  70,
  27.00,
  27.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_964,
  'Com extensão',
  NULL,
  NULL,
  100,
  38.00,
  38.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_964,
  'Manutenção Cliente Fada',
  NULL,
  NULL,
  90,
  30.00,
  30.00,
  2,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_964,
  'Manutenção de profissional externa',
  NULL,
  NULL,
  110,
  30.00,
  30.00,
  3,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_964,
  'Reparação',
  NULL,
  NULL,
  20,
  4.00,
  4.00,
  4,
  0,
  'addon',
  @now,
  @now
);

-- Serviço: Remoção (oculto)
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_11,
  'Remoção',
  NULL,
  NULL,
  20,
  8.00,
  8.00,
  5,
  1,
  @now,
  @now,
  NULL
);
SET @svc_965 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_965,
  'Verniz Gel',
  NULL,
  NULL,
  20,
  8.00,
  8.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_965,
  'Unhas de Gel',
  NULL,
  NULL,
  30,
  10.00,
  10.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_965,
  'Unhas de Acrílico',
  NULL,
  NULL,
  30,
  10.00,
  10.00,
  2,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Reparação (oculto)
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_11,
  'Reparação',
  NULL,
  NULL,
  10,
  3.00,
  3.00,
  6,
  1,
  @now,
  @now,
  NULL
);
SET @svc_966 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_966,
  'Verniz Gel',
  NULL,
  NULL,
  10,
  3.00,
  3.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_966,
  'Unhas de Gel',
  NULL,
  NULL,
  15,
  4.00,
  4.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_966,
  'Unhas de Acrílico',
  NULL,
  NULL,
  20,
  4.00,
  4.00,
  2,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Tradicional
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_12,
  'Tradicional',
  NULL,
  NULL,
  45,
  20.00,
  20.00,
  7,
  0,
  @now,
  @now,
  NULL
);
SET @svc_967 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_967,
  'Tradicional',
  NULL,
  NULL,
  45,
  20.00,
  20.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_967,
  'Tradicional Masculina',
  NULL,
  NULL,
  60,
  22.00,
  22.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_967,
  'Spa',
  NULL,
  NULL,
  15,
  6.00,
  6.00,
  2,
  0,
  'addon',
  @now,
  @now
);

-- Serviço: Verniz Gel
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_12,
  'Verniz Gel',
  NULL,
  NULL,
  60,
  25.00,
  25.00,
  8,
  0,
  @now,
  @now,
  NULL
);
SET @svc_968 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_968,
  'Verniz Gel',
  NULL,
  NULL,
  60,
  25.00,
  25.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_968,
  'Spa',
  NULL,
  NULL,
  15,
  6.00,
  6.00,
  1,
  0,
  'addon',
  @now,
  @now
);

-- Serviço: Remoção (oculto)
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_12,
  'Remoção',
  NULL,
  NULL,
  20,
  8.00,
  8.00,
  9,
  1,
  @now,
  @now,
  NULL
);
SET @svc_969 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_969,
  'Verniz Gel',
  NULL,
  NULL,
  20,
  8.00,
  8.00,
  0,
  1,
  'choice',
  @now,
  @now
);

-- Serviço: Reparação (oculto)
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_12,
  'Reparação',
  NULL,
  NULL,
  10,
  4.00,
  4.00,
  10,
  1,
  @now,
  @now,
  NULL
);
SET @svc_970 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_970,
  'Verniz Gel',
  NULL,
  NULL,
  10,
  4.00,
  4.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_970,
  'Unhas de Gel',
  NULL,
  NULL,
  10,
  4.00,
  4.00,
  1,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Extensão
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_13,
  'Extensão',
  NULL,
  NULL,
  140,
  35.00,
  35.00,
  11,
  0,
  @now,
  @now,
  NULL
);
SET @svc_971 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_971,
  'Fio a Fio Clássico',
  NULL,
  NULL,
  140,
  35.00,
  35.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_971,
  'Volume Brasileiro',
  NULL,
  NULL,
  100,
  35.00,
  35.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_971,
  'Volume 3D',
  NULL,
  NULL,
  90,
  40.00,
  40.00,
  2,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_971,
  'Volume Russo',
  NULL,
  NULL,
  100,
  40.00,
  40.00,
  3,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_971,
  'Mega Volume',
  NULL,
  NULL,
  110,
  45.00,
  45.00,
  4,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_971,
  'Pestanas Castanhas',
  NULL,
  NULL,
  100,
  35.00,
  35.00,
  5,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Manutenção
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_13,
  'Manutenção',
  NULL,
  NULL,
  120,
  25.00,
  25.00,
  12,
  0,
  @now,
  @now,
  NULL
);
SET @svc_972 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_972,
  'Fio a Fio Clássico',
  NULL,
  NULL,
  120,
  25.00,
  25.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_972,
  'Volume Brasileiro',
  NULL,
  NULL,
  95,
  27.00,
  27.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_972,
  'Volume 3D',
  NULL,
  NULL,
  95,
  27.00,
  27.00,
  2,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_972,
  'Volume Russo',
  NULL,
  NULL,
  95,
  27.00,
  27.00,
  3,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_972,
  'Mega Volume',
  NULL,
  NULL,
  95,
  35.00,
  35.00,
  4,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_972,
  'Pestanas Castanhas',
  NULL,
  NULL,
  100,
  27.00,
  27.00,
  5,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Lash Lifting
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_13,
  'Lash Lifting',
  NULL,
  NULL,
  45,
  30.00,
  30.00,
  13,
  0,
  @now,
  @now,
  NULL
);
SET @svc_973 := LAST_INSERT_ID();

-- Serviço: Remoção (oculto)
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_13,
  'Remoção',
  NULL,
  NULL,
  45,
  5.00,
  5.00,
  14,
  1,
  @now,
  @now,
  NULL
);
SET @svc_974 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_974,
  'Remoção de extensão de pestanas',
  NULL,
  NULL,
  45,
  5.00,
  5.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_974,
  'Remoção de extensão de pestanas (profissional externa)',
  NULL,
  NULL,
  35,
  10.00,
  10.00,
  1,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Sobrancelhas
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_14,
  'Sobrancelhas',
  NULL,
  NULL,
  30,
  15.00,
  15.00,
  15,
  0,
  @now,
  @now,
  NULL
);
SET @svc_975 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_975,
  'Design Personalizado',
  NULL,
  NULL,
  30,
  15.00,
  15.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_975,
  'Brow Lamination',
  NULL,
  NULL,
  80,
  35.00,
  35.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_975,
  'Henna',
  NULL,
  NULL,
  35,
  5.00,
  5.00,
  2,
  0,
  'addon',
  @now,
  @now
);

-- Serviço: Buço
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_14,
  'Buço',
  NULL,
  NULL,
  15,
  5.00,
  5.00,
  16,
  0,
  @now,
  @now,
  NULL
);
SET @svc_976 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_976,
  'Depilação a cera',
  NULL,
  NULL,
  15,
  5.00,
  5.00,
  0,
  1,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_976,
  'Epilação à linha',
  NULL,
  NULL,
  15,
  0.00,
  0.00,
  1,
  0,
  'choice',
  @now,
  @now
);

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_976,
  'Dermaplaning',
  NULL,
  NULL,
  15,
  5.00,
  5.00,
  2,
  0,
  'choice',
  @now,
  @now
);

-- Serviço: Hidratação labial
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_14,
  'Hidratação labial',
  NULL,
  NULL,
  30,
  15.00,
  15.00,
  17,
  0,
  @now,
  @now,
  NULL
);
SET @svc_977 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_977,
  'Hidratação labial',
  NULL,
  NULL,
  30,
  15.00,
  15.00,
  0,
  1,
  'choice',
  @now,
  @now
);

-- Serviço: Dermaplaning Facial
INSERT INTO `services` (`organization_id`, `store_id`, `category_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `hidden_from_booking`, `created_at`, `updated_at`, `deleted_at`) VALUES (
  @organization_id,
  @store_id,
  @cat_14,
  'Dermaplaning Facial',
  NULL,
  NULL,
  45,
  24.00,
  24.00,
  18,
  0,
  @now,
  @now,
  NULL
);
SET @svc_978 := LAST_INSERT_ID();

INSERT INTO `service_options` (`service_id`, `name`, `description`, `short_description`, `duration`, `price`, `online_price`, `sort_order`, `is_baseline`, `type`, `created_at`, `updated_at`) VALUES (
  @svc_978,
  'Dermaplaning Facial',
  NULL,
  NULL,
  45,
  24.00,
  24.00,
  0,
  1,
  'choice',
  @now,
  @now
);

-- ---------------------------------------------------------------------------
-- 5) Ligações service_extra (sem técnicas)
-- ---------------------------------------------------------------------------
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_961, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_961, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_961, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_961, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_961, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_961, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_962, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_962, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_962, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_962, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_962, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_962, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_963, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_963, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_963, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_963, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_963, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_963, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_964, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_964, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_964, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_964, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_964, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_964, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_965, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_965, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_965, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_965, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_965, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_965, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_966, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_966, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_966, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_966, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_966, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_966, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_967, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_967, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_967, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_967, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_967, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_967, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_968, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_968, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_968, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_968, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_968, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_968, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_969, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_969, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_969, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_969, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_969, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_969, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_970, @extra_7, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_970, @extra_8, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_970, @extra_9, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_970, @extra_10, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_970, @extra_11, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_970, @extra_12, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_971, @extra_13, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_971, @extra_14, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_971, @extra_15, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_971, @extra_16, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_971, @extra_17, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_972, @extra_13, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_972, @extra_14, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_972, @extra_15, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_972, @extra_16, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_972, @extra_17, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_973, @extra_13, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_973, @extra_14, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_973, @extra_15, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_973, @extra_16, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_973, @extra_17, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_974, @extra_13, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_974, @extra_14, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_974, @extra_15, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_974, @extra_16, @now, @now
);
INSERT INTO `service_extra` (`service_id`, `extra_id`, `created_at`, `updated_at`) VALUES (
  @svc_974, @extra_17, @now, @now
);

COMMIT;

-- Fim. Verifica no CRM: 4 categorias ocultas, Remoção/Reparação ocultas, extras ligados.
