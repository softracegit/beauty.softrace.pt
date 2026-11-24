-- =============================================================================
-- UPDATE frases curtas + descrições (serviços, variantes, extras)
-- Casa por NOME (categoria + serviço [+ variante] / categoria extra + extra)
-- ANTES: SET @organization_id := <id da org no servidor>;
-- =============================================================================

SET NAMES utf8mb4;
SET @organization_id := 1;  -- << ALTERA
SET @now := NOW();

START TRANSACTION;

-- --- Manicure NOVO ---
UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Unhas arranjadas e verniz normal. O clássico, rápido e sem gel.',
    s.description = '• Limar e dar forma às unhas
• Tratamento de cutículas
• Verniz tradicional na cor que escolheres (ou só base de brilho)',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Tradicional'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Unhas arranjadas e verniz normal. O clássico, rápido e sem gel.',
    o.description = '• Limar e dar forma às unhas
• Tratamento de cutículas
• Verniz tradicional na cor que escolheres (ou só base de brilho)',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Tradicional'
  AND o.name = 'Tradicional'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Cuidados das unhas com verniz normal, versão masculina.',
    o.description = '• Limar e dar forma às unhas
• Tratamento de cutículas
• Verniz ou acabamento natural',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Tradicional'
  AND o.name = 'Tradicional Masculina'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Cor que dura semanas, sobre a tua unha natural, sem alongar.',
    s.description = '• Limar e dar forma
• Tratamento de cutículas
• Base, cor e top em gel, secos em cabine UV/LED
• Acabamento brilhante',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Cor que dura semanas, sobre a tua unha natural, sem alongar.',
    o.description = '• Limar e dar forma
• Tratamento de cutículas
• Base, cor e top em gel, secos em cabine UV/LED
• Acabamento brilhante',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Verniz Gel'
  AND o.name = 'Verniz Gel / Gelinho'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Substituição de uma unha partida ou danificada.',
    o.description = '• Avaliação da unha
• Reparação pontual
• Acabamento alinhado com o resto',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Verniz Gel'
  AND o.name = 'Reparação'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Gel para reforçar ou alongar: escolhe natural, extensão ou manutenção.',
    s.description = '• Preparação da unha e cutículas
• Aplicação ou manutenção em gel, conforme a opção
• Forma e cor à escolha',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Gel sobre a tua unha para a reforçar, sem alongar.',
    o.description = '• Preparação da unha e cutículas
• Camada de gel de construção para dar resistência
• Forma e cor à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Gel'
  AND o.name = 'Sobre unha natural'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Alongamento em gel, no comprimento e formato que escolheres.',
    o.description = '• Preparação da unha e cutículas
• Extensão em gel até ao comprimento escolhido
• Forma (quadrada, amendoada, stiletto…) e cor à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Gel'
  AND o.name = 'Com extensão'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Já tens gel? Preenchemos o crescimento e renovamos a cor.',
    o.description = '• Limagem do gel existente
• Preenchimento da zona que cresceu
• Correção da forma e cutículas
• Cor nova à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Gel'
  AND o.name = 'Manutenção Cliente Fada'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção de gel feito noutro salão: preenchimento e cor nova.',
    o.description = '• Avaliação do trabalho existente
• Limagem e preparação
• Preenchimento da zona que cresceu
• Correção da forma e cutículas
• Cor nova à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Gel'
  AND o.name = 'Manutenção de profissional externa'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Substituição de uma unha de gel partida.',
    o.description = '• Avaliação da unha
• Reparação pontual em gel
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Gel'
  AND o.name = 'Reparação'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Acrílico de alta resistência: natural, extensão ou manutenção.',
    s.description = '• Preparação da unha e cutículas
• Aplicação ou manutenção em acrílico, conforme a opção
• Forma e cor à escolha',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Acrílico'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Acrílico sobre a tua unha: máxima resistência, sem alongar.',
    o.description = '• Preparação da unha e cutículas
• Camada de acrílico para dar resistência
• Forma e cor à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Acrílico'
  AND o.name = 'Sobre unha natural'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Alongamento em acrílico, no comprimento e formato que escolheres.',
    o.description = '• Preparação da unha e cutículas
• Extensão em acrílico até ao comprimento escolhido
• Forma e cor à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Acrílico'
  AND o.name = 'Com extensão'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Já tens acrílico? Preenchemos o crescimento e renovamos a cor.',
    o.description = '• Limagem do acrílico existente
• Preenchimento da zona que cresceu
• Correção da forma e cutículas
• Cor nova à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Acrílico'
  AND o.name = 'Manutenção Cliente Fada'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção de acrílico feito noutro salão.',
    o.description = '• Avaliação do trabalho existente
• Limagem e preparação
• Preenchimento da zona que cresceu
• Correção da forma e cutículas
• Cor nova à escolha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Acrílico'
  AND o.name = 'Manutenção de profissional externa'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Substituição de uma unha de acrílico partida.',
    o.description = '• Avaliação da unha
• Reparação pontual em acrílico
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Unhas de Acrílico'
  AND o.name = 'Reparação'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Remoção segura do material, sem danificar a unha natural.',
    s.description = '• Remoção com produto adequado ao tipo de material
• Limpeza e preparação da unha',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Remoção'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção segura do verniz gel, sem danificar a unha.',
    o.description = '• Remoção do verniz gel
• Limpeza e preparação da unha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Remoção'
  AND o.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção segura das unhas de gel.',
    o.description = '• Remoção do gel com produto adequado
• Limpeza e preparação da unha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Remoção'
  AND o.name = 'Unhas de Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção segura das unhas de acrílico.',
    o.description = '• Remoção do acrílico com produto adequado
• Limpeza e preparação da unha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Remoção'
  AND o.name = 'Unhas de Acrílico'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Reparação pontual de uma unha danificada.',
    s.description = '• Avaliação da unha
• Reparação pontual
• Acabamento alinhado com o resto',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Reparação'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Reparação pontual de verniz gel.',
    o.description = '• Avaliação da unha
• Reparação pontual
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Reparação'
  AND o.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Reparação pontual de unha de gel.',
    o.description = '• Avaliação da unha
• Reparação pontual em gel
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Reparação'
  AND o.name = 'Unhas de Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Reparação pontual de unha de acrílico.',
    o.description = '• Avaliação da unha
• Reparação pontual em acrílico
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Manicure NOVO'
  AND s.name = 'Reparação'
  AND o.name = 'Unhas de Acrílico'
  AND s.deleted_at IS NULL;

-- --- Pedicure NOVO ---
UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Pés cuidados e verniz normal.',
    s.description = '• Preparação dos pés
• Corte, lima e cutículas
• Remoção de calosidades
• Hidratação
• Verniz tradicional na cor que escolheres',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Tradicional'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Pés cuidados e verniz normal.',
    o.description = '• Preparação dos pés
• Corte, lima e cutículas
• Remoção de calosidades
• Hidratação
• Verniz tradicional na cor que escolheres',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Tradicional'
  AND o.name = 'Tradicional'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Cuidados dos pés com verniz ou acabamento natural.',
    o.description = '• Preparação dos pés
• Corte, lima e cutículas
• Remoção de calosidades
• Hidratação
• Verniz ou acabamento natural',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Tradicional'
  AND o.name = 'Tradicional Masculina'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Momento spa nos pés: esfoliação e hidratação extra.',
    o.description = '• Esfoliação
• Máscara ou hidratação intensiva',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Tradicional'
  AND o.name = 'Spa'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Pedicure completa com cor que dura semanas.',
    s.description = '• Tudo o que está na Pedicure Tradicional
• Base, cor e top em gel, secos em cabine UV/LED',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Pedicure completa com cor que dura semanas.',
    o.description = '• Tudo o que está na Pedicure Tradicional
• Base, cor e top em gel, secos em cabine UV/LED',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Verniz Gel'
  AND o.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Momento spa nos pés: esfoliação e hidratação extra.',
    o.description = '• Esfoliação
• Máscara ou hidratação intensiva',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Verniz Gel'
  AND o.name = 'Spa'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Remoção segura do verniz gel dos pés.',
    s.description = '• Remoção do verniz gel
• Limpeza da unha',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Remoção'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção segura do verniz gel dos pés.',
    o.description = '• Remoção do verniz gel
• Limpeza da unha',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Remoção'
  AND o.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Reparação pontual de uma unha dos pés.',
    s.description = '• Avaliação da unha
• Reparação pontual
• Acabamento',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Reparação'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Reparação pontual de verniz gel nos pés.',
    o.description = '• Avaliação da unha
• Reparação pontual
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Reparação'
  AND o.name = 'Verniz Gel'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Reparação pontual de unha de gel nos pés.',
    o.description = '• Avaliação da unha
• Reparação pontual em gel
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pedicure NOVO'
  AND s.name = 'Reparação'
  AND o.name = 'Unhas de Gel'
  AND s.deleted_at IS NULL;

-- --- Pestanas NOVO ---
UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Extensões de pestanas: do olhar natural ao máximo volume.',
    s.description = '• Aplicação de extensões no estilo escolhido
• Comprimento e curvatura à tua medida',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Uma extensão por pestana: olhar realçado e natural.',
    o.description = '• Aplicação fio a fio em cada pestana natural
• Comprimento e curvatura à tua medida',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND o.name = 'Fio a Fio Clássico'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Mais densidade do que o fio a fio, ainda com ar leve.',
    o.description = '• Aplicação em leques leves sobre cada pestana
• Comprimento e curvatura à tua medida',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND o.name = 'Volume Brasileiro'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Mais densidade do que o fio a fio, ainda com ar leve.',
    o.description = '• Aplicação em leques leves sobre cada pestana
• Comprimento e curvatura à tua medida',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND o.name = 'Volume 3D'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Volume cheio e marcado, para um olhar intenso.',
    o.description = '• Leques feitos à mão com vários fios finos por pestana',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND o.name = 'Volume Russo'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'O máximo de volume: olhar dramático.',
    o.description = '• Leques densos com muitos fios ultrafinos por pestana',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND o.name = 'Mega Volume'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Extensão em tons castanhos, para um olhar suave.',
    o.description = '• Aplicação em tons de castanho
• Comprimento e curvatura à tua medida',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Extensão'
  AND o.name = 'Pestanas Castanhas'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Já tens extensões? Renovamos o crescimento e o volume.',
    s.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de comprimento e volume',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção do fio a fio: renovamos o crescimento.',
    o.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de comprimento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND o.name = 'Fio a Fio Clássico'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção do Volume Brasileiro: renovamos densidade e forma.',
    o.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de volume e comprimento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND o.name = 'Volume Brasileiro'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção do Volume 3D: renovamos densidade e forma.',
    o.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de volume e comprimento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND o.name = 'Volume 3D'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção do Volume Russo: renovamos o olhar intenso.',
    o.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de volume e comprimento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND o.name = 'Volume Russo'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção do Mega Volume: renovamos o máximo de volume.',
    o.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de volume e comprimento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND o.name = 'Mega Volume'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Manutenção das pestanas castanhas: renovamos o olhar suave.',
    o.description = '• Remoção de fios soltos
• Preenchimento do crescimento
• Ajuste de comprimento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Manutenção'
  AND o.name = 'Pestanas Castanhas'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Curva e realce às tuas pestanas naturais, sem extensões. Até 6 semanas.',
    s.description = '• Lifting das pestanas naturais
• Tinta incluída
• Resultado dura até 6 semanas',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Lash Lifting'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Remoção segura das extensões, sem estragar a pestana natural.',
    s.description = '• Remoção com produto próprio',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Remoção'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção segura das extensões, sem estragar a pestana natural.',
    o.description = '• Remoção com produto próprio',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Remoção'
  AND o.name = 'Remoção de extensão de pestanas'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção de extensões feitas noutro salão.',
    o.description = '• Avaliação da pestana natural
• Remoção segura com produto próprio',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Pestanas NOVO'
  AND s.name = 'Remoção'
  AND o.name = 'Remoção de extensão de pestanas (profissional externa)'
  AND s.deleted_at IS NULL;

-- --- Rosto NOVO ---
UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Desenho das sobrancelhas à medida do teu rosto.',
    s.description = '• Análise do formato do rosto
• Depilação e desenho
• Acabamento',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Sobrancelhas'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Desenho à medida do teu rosto.',
    o.description = '• Análise do formato do rosto
• Depilação e desenho
• Acabamento (dura até cerca de 20 dias)',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Sobrancelhas'
  AND o.name = 'Design Personalizado'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Sobrancelhas alinhadas e mais cheias, sem maquilhagem. Até 6 semanas.',
    o.description = '• Laminação dos pelos
• Resultado dura até 6 semanas',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Sobrancelhas'
  AND o.name = 'Brow Lamination'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Cor e preenchimento nas sobrancelhas com henna.',
    o.description = '• Aplicação de henna
• A cor fica 5 a 10 dias na pele',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Sobrancelhas'
  AND o.name = 'Henna'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Remoção rápida e precisa do buço.',
    s.description = '• Limpeza da zona
• Método escolhido (cera, linha ou dermaplaning)
• Acabamento',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Buço'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção do buço a cera, rápida e precisa.',
    o.description = '• Limpeza da zona
• Depilação a cera
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Buço'
  AND o.name = 'Depilação a cera'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção do buço à linha, definição limpa.',
    o.description = '• Limpeza da zona
• Epilação à linha
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Buço'
  AND o.name = 'Epilação à linha'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remoção do buço por dermaplaning, pele lisa.',
    o.description = '• Limpeza da zona
• Dermaplaning
• Acabamento',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Buço'
  AND o.name = 'Dermaplaning'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Lábios hidratados, suaves e com cuidado intensivo.',
    s.description = '• Limpeza
• Esfoliação suave
• Máscara ou hidratação intensiva',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Hidratação labial'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Lábios hidratados, suaves e com cuidado intensivo.',
    o.description = '• Limpeza
• Esfoliação suave
• Máscara ou hidratação intensiva',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Hidratação labial'
  AND o.name = 'Hidratação labial'
  AND s.deleted_at IS NULL;

UPDATE `services` s
INNER JOIN `categories` c ON c.id = s.category_id
SET s.short_description = 'Remove pelinhos e células mortas: pele lisa e maquilhagem perfeita.',
    s.description = '• Limpeza da pele
• Esfoliação com lâmina
• Hidratação',
    s.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Dermaplaning Facial'
  AND s.deleted_at IS NULL;

UPDATE `service_options` o
INNER JOIN `services` s ON s.id = o.service_id
INNER JOIN `categories` c ON c.id = s.category_id
SET o.short_description = 'Remove pelinhos e células mortas: pele lisa e maquilhagem perfeita.',
    o.description = '• Limpeza da pele
• Esfoliação com lâmina
• Hidratação',
    o.updated_at = @now
WHERE c.organization_id = @organization_id
  AND c.name = 'Rosto NOVO'
  AND s.name = 'Dermaplaning Facial'
  AND o.name = 'Dermaplaning Facial'
  AND s.deleted_at IS NULL;

-- --- Extras ---
UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Decoração simples: um detalhe elegante nas unhas.',
    e.description = '• Motivo simples ou accent nail
• Aplicado sobre o serviço de unhas escolhido',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Nail Art'
  AND e.name = 'Nail Art Básico'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Decoração elaborada, com mais detalhe e presença.',
    e.description = '• Desenho avançado em uma ou mais unhas
• Aplicado sobre o serviço de unhas escolhido',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Nail Art'
  AND e.name = 'Nail Art Avançado'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Nail art à tua medida: descreves o look, nós criamos.',
    e.description = '• Decoração personalizada sob consulta
• Aplicado sobre o serviço de unhas escolhido',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Nail Art'
  AND e.name = 'Nail Art Personalizado'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Look “molhado”: brilho intenso e pestanas coladas no efeito glossy.',
    e.description = '• Aplicação com desenho efeito molhado
• Acabamento glossy característico',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Pestanas'
  AND e.name = 'Efeito Molhado'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Fox — olhar puxado para cima, estilo “cat eye”.',
    e.description = '• Aplicação com desenho efeito Fox
• Ênfase no canto externo',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Pestanas'
  AND e.name = 'Efeito Fox'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Wispy — fios irregulares para um olhar texturado e leve.',
    e.description = '• Aplicação com desenho efeito Wispy
• Comprimentos mistos para textura',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Pestanas'
  AND e.name = 'Efeito Wispy'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Kim Kardashian — volume glamouroso e marcante.',
    e.description = '• Aplicação com desenho estilo Kim Kardashian
• Volume e definição no olhar',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Pestanas'
  AND e.name = 'Efeito Kim Kardashian'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Anime — pestanas abertas e dramáticas, estilo cartoon.',
    e.description = '• Aplicação com desenho efeito Anime
• Ênfase no canto interno e abertura do olhar',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Pestanas'
  AND e.name = 'Efeito Anime'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Francesinha clássica ou moderna, no tom que preferires.',
    e.description = '• Aplicação de francesinha
• Tom e espessura da linha à escolha',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Acabamentos e efeitos'
  AND e.name = 'Francesinha'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Olho de gato: brilho metálico que muda com a luz.',
    e.description = '• Aplicação com efeito olho de gato
• Acabamento metálico com íman',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Acabamentos e efeitos'
  AND e.name = 'Olho de Gato'
  AND e.deleted_at IS NULL;

UPDATE `extras` e
INNER JOIN `extra_categories` ec ON ec.id = e.extra_category_id
SET e.short_description = 'Baby Color / Boomer: degradê suave de cor na unha.',
    e.description = '• Aplicação em degradê suave
• Transição de cor delicada',
    e.updated_at = @now
WHERE ec.organization_id = @organization_id
  AND ec.name = 'Acabamentos e efeitos'
  AND e.name = 'Baby Color Boomer'
  AND e.deleted_at IS NULL;

COMMIT;

-- Fim.
