-- ============================================
-- HackConcursos - Update V2 (Estratégia Global)
-- ============================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- 1. Expansão da tabela de usuários
ALTER TABLE `usuarios` 
ADD COLUMN `token_saldo` INT UNSIGNED DEFAULT 0 AFTER `plano`,
ADD COLUMN `stripe_id` VARCHAR(255) DEFAULT NULL AFTER `token_saldo`,
ADD COLUMN `stripe_subscription_id` VARCHAR(255) DEFAULT NULL AFTER `stripe_id`,
ADD COLUMN `status_assinatura` VARCHAR(50) DEFAULT 'inactive' AFTER `stripe_subscription_id`,
ADD COLUMN `expira_em` DATETIME DEFAULT NULL AFTER `status_assinatura`;

-- 2. Tabela de Biblioteca Global de Editais (Curadoria Admin)
CREATE TABLE IF NOT EXISTS `biblioteca_editais` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome_concurso` VARCHAR(255) NOT NULL,
  `orgao` VARCHAR(150) NOT NULL,
  `banca` VARCHAR(100) NOT NULL,
  `ano` YEAR NOT NULL,
  `categoria` ENUM('Nacional', 'Estadual', 'Municipal', 'Outros') DEFAULT 'Nacional',
  `status` ENUM('aberto', 'previsto', 'encerrado') DEFAULT 'aberto',
  `imagem_capa` VARCHAR(255) DEFAULT NULL,
  `arquivo_edital` VARCHAR(255) DEFAULT NULL,
  `slug` VARCHAR(255) UNIQUE NOT NULL,
  `popularidade` INT UNSIGNED DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Matérias/Disciplinas da Biblioteca
CREATE TABLE IF NOT EXISTS `biblioteca_disciplinas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `biblioteca_edital_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `peso_padrao` DECIMAL(5,2) DEFAULT 1.00,
  `importancia_ia` TINYINT UNSIGNED DEFAULT 5, -- Nível de importância de 1 a 10 definido pela IA
  FOREIGN KEY (`biblioteca_edital_id`) REFERENCES `biblioteca_editais`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabela de Histórico/Transações de Tokens
CREATE TABLE IF NOT EXISTS `token_transacoes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('compra', 'consumo', 'bonus') NOT NULL,
  `quantidade` INT NOT NULL, -- Positivo para compra/bonus, negativo para consumo
  `servico` VARCHAR(100) DEFAULT NULL, -- Ex: 'IA Mentor', 'Recalculo Plano'
  `descricao` TEXT DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabela de Ofertas Dinâmicas (Sistema de Gatilhos)
CREATE TABLE IF NOT EXISTS `ofertas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(255) NOT NULL,
  `descricao` TEXT NOT NULL,
  `preco` DECIMAL(10,2) NOT NULL,
  `tipo_desconto` ENUM('porcentagem', 'valor_fixo') DEFAULT 'valor_fixo',
  `valor_desconto` DECIMAL(10,2) DEFAULT 0.00,
  `gatilho_tipo` VARCHAR(50) NOT NULL, -- Ex: 'falha_simulado', 'inatividade'
  `gatilho_valor` VARCHAR(100) DEFAULT NULL, -- Ex: '0.6' (60% de erro)
  `link_stripe` VARCHAR(500) DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabela de Rastreamento de Eventos (Para Remarketing e Gatilhos)
CREATE TABLE IF NOT EXISTS `eventos_usuario` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `evento` VARCHAR(100) NOT NULL, -- Ex: 'login', 'clique_ia', 'erro_questao'
  `metadata` JSON DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Vínculo entre Usuário e Edital da Biblioteca e Onboarding
ALTER TABLE `perfis_usuario` 
ADD COLUMN `biblioteca_edital_id` INT UNSIGNED DEFAULT NULL AFTER `usuario_id`,
ADD COLUMN `onboarding_visto` TINYINT(1) DEFAULT 0 AFTER `nivel_geral`,
ADD CONSTRAINT `fk_perfil_biblioteca` FOREIGN KEY (`biblioteca_edital_id`) REFERENCES `biblioteca_editais`(`id`) ON DELETE SET NULL;

SET foreign_key_checks = 1;
