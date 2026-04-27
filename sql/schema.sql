-- ============================================
-- HackConcursos - Schema Completo MySQL
-- Versão 1.0 - Modo Guerra
-- ============================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- =====================
-- TABELA: usuarios
-- =====================
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `perfil` ENUM('aluno','admin') NOT NULL DEFAULT 'aluno',
  `plano` ENUM('free','basico','premium') NOT NULL DEFAULT 'free',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `token_recuperacao` VARCHAR(64) DEFAULT NULL,
  `token_expira` DATETIME DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: perfis_usuario
-- =====================
CREATE TABLE IF NOT EXISTS `perfis_usuario` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `horas_dia` DECIMAL(4,2) DEFAULT 2.00,
  `dias_semana` VARCHAR(20) DEFAULT '1,2,3,4,5',
  `dia_folga` TINYINT(1) DEFAULT 0,
  `objetivo` VARCHAR(255) DEFAULT NULL,
  `nivel_geral` ENUM('iniciante','intermediario','avancado') DEFAULT 'iniciante',
  `sequencia_dias` INT UNSIGNED DEFAULT 0,
  `total_horas` DECIMAL(10,2) DEFAULT 0.00,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: editais
-- =====================
CREATE TABLE IF NOT EXISTS `editais` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `nome_concurso` VARCHAR(255) NOT NULL,
  `banca` VARCHAR(100) DEFAULT NULL,
  `orgao` VARCHAR(150) DEFAULT NULL,
  `data_prova` DATE DEFAULT NULL,
  `arquivo_pdf` VARCHAR(255) NOT NULL,
  `conteudo_texto` LONGTEXT DEFAULT NULL,
  `status_processamento` ENUM('pendente','processando','concluido','erro') DEFAULT 'pendente',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: cargos
-- =====================
CREATE TABLE IF NOT EXISTS `cargos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `edital_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `nivel` VARCHAR(50) DEFAULT NULL,
  `vagas` INT UNSIGNED DEFAULT 0,
  `salario` DECIMAL(10,2) DEFAULT NULL,
  `selecionado` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`edital_id`) REFERENCES `editais`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: disciplinas
-- =====================
CREATE TABLE IF NOT EXISTS `disciplinas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cargo_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `peso` DECIMAL(5,2) DEFAULT 1.00,
  `horas_sugeridas` DECIMAL(6,2) DEFAULT 10.00,
  FOREIGN KEY (`cargo_id`) REFERENCES `cargos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: topicos_edital
-- =====================
CREATE TABLE IF NOT EXISTS `topicos_edital` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `disciplina_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `cobrado_frequentemente` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: diagnosticos
-- =====================
CREATE TABLE IF NOT EXISTS `diagnosticos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `cargo_id` INT UNSIGNED NOT NULL,
  `horas_disponivel` DECIMAL(4,2) NOT NULL DEFAULT 2.00,
  `dias_estudo` VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
  `dia_folga` TINYINT(1) DEFAULT 7,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cargo_id`) REFERENCES `cargos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: diagnostico_disciplinas
-- =====================
CREATE TABLE IF NOT EXISTS `diagnostico_disciplinas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `diagnostico_id` INT UNSIGNED NOT NULL,
  `disciplina_id` INT UNSIGNED NOT NULL,
  `nivel` ENUM('iniciante','intermediario','avancado') DEFAULT 'iniciante',
  `dificuldade` TINYINT UNSIGNED DEFAULT 5,
  FOREIGN KEY (`diagnostico_id`) REFERENCES `diagnosticos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: planos_estudo
-- =====================
CREATE TABLE IF NOT EXISTS `planos_estudo` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `cargo_id` INT UNSIGNED NOT NULL,
  `diagnostico_id` INT UNSIGNED DEFAULT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cargo_id`) REFERENCES `cargos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: tarefas_estudo
-- =====================
CREATE TABLE IF NOT EXISTS `tarefas_estudo` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plano_id` INT UNSIGNED NOT NULL,
  `disciplina_id` INT UNSIGNED NOT NULL,
  `topico_id` INT UNSIGNED DEFAULT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `tipo` ENUM('estudo','revisao_24h','revisao_7d','revisao_30d','simulado') DEFAULT 'estudo',
  `data_prevista` DATE NOT NULL,
  `duracao_minutos` INT UNSIGNED DEFAULT 60,
  `concluida` TINYINT(1) DEFAULT 0,
  `concluida_em` DATETIME DEFAULT NULL,
  `atrasada` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`plano_id`) REFERENCES `planos_estudo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: revisoes
-- =====================
CREATE TABLE IF NOT EXISTS `revisoes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tarefa_origem_id` INT UNSIGNED NOT NULL,
  `tarefa_revisao_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('24h','7d','30d') NOT NULL,
  FOREIGN KEY (`tarefa_origem_id`) REFERENCES `tarefas_estudo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tarefa_revisao_id`) REFERENCES `tarefas_estudo`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: progresso_usuario
-- =====================
CREATE TABLE IF NOT EXISTS `progresso_usuario` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `plano_id` INT UNSIGNED NOT NULL,
  `disciplina_id` INT UNSIGNED NOT NULL,
  `percentual_concluido` DECIMAL(5,2) DEFAULT 0.00,
  `horas_estudadas` DECIMAL(8,2) DEFAULT 0.00,
  `ultima_atividade` DATE DEFAULT NULL,
  UNIQUE KEY `uk_progresso` (`usuario_id`,`plano_id`,`disciplina_id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plano_id`) REFERENCES `planos_estudo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: simulados
-- =====================
CREATE TABLE IF NOT EXISTS `simulados` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `cargo_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(255) DEFAULT 'Simulado',
  `total_questoes` INT UNSIGNED DEFAULT 0,
  `acertos` INT UNSIGNED DEFAULT 0,
  `erros` INT UNSIGNED DEFAULT 0,
  `tempo_minutos` INT UNSIGNED DEFAULT 0,
  `concluido` TINYINT(1) DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cargo_id`) REFERENCES `cargos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: questoes
-- =====================
CREATE TABLE IF NOT EXISTS `questoes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `simulado_id` INT UNSIGNED NOT NULL,
  `disciplina_id` INT UNSIGNED NOT NULL,
  `enunciado` TEXT NOT NULL,
  `alternativa_a` TEXT NOT NULL,
  `alternativa_b` TEXT NOT NULL,
  `alternativa_c` TEXT NOT NULL,
  `alternativa_d` TEXT NOT NULL,
  `alternativa_e` TEXT DEFAULT NULL,
  `gabarito` CHAR(1) NOT NULL,
  `explicacao` TEXT DEFAULT NULL,
  FOREIGN KEY (`simulado_id`) REFERENCES `simulados`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: respostas_usuario
-- =====================
CREATE TABLE IF NOT EXISTS `respostas_usuario` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `simulado_id` INT UNSIGNED NOT NULL,
  `questao_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `resposta` CHAR(1) DEFAULT NULL,
  `correta` TINYINT(1) DEFAULT 0,
  `respondida_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`simulado_id`) REFERENCES `simulados`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`questao_id`) REFERENCES `questoes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: mensagens_chat
-- =====================
CREATE TABLE IF NOT EXISTS `mensagens_chat` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `edital_id` INT UNSIGNED NOT NULL,
  `papel` ENUM('user','model') NOT NULL,
  `mensagem` TEXT NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`edital_id`) REFERENCES `editais`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: produtos
-- =====================
CREATE TABLE IF NOT EXISTS `produtos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `disciplina_tag` VARCHAR(100) DEFAULT NULL,
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `imagem` VARCHAR(255) DEFAULT NULL,
  `link_compra` VARCHAR(500) DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: carrinho
-- =====================
CREATE TABLE IF NOT EXISTS `carrinho` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `produto_id` INT UNSIGNED NOT NULL,
  `quantidade` INT UNSIGNED DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: pedidos
-- =====================
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pendente','pago','cancelado') DEFAULT 'pendente',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: pedido_itens
-- =====================
CREATE TABLE IF NOT EXISTS `pedido_itens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT UNSIGNED NOT NULL,
  `produto_id` INT UNSIGNED NOT NULL,
  `quantidade` INT UNSIGNED DEFAULT 1,
  `preco_unitario` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: conquistas
-- =====================
CREATE TABLE IF NOT EXISTS `conquistas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `titulo` VARCHAR(100) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `icone` VARCHAR(10) DEFAULT '🏆',
  `conquistada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- TABELA: configuracoes
-- =====================
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `chave` VARCHAR(100) NOT NULL UNIQUE,
  `valor` TEXT DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- DADOS INICIAIS
-- =====================
INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`) VALUES
('gemini_api_key', '', 'Chave da API Google Gemini'),
('nome_plataforma', 'HackConcursos', 'Nome da plataforma'),
('email_contato', 'contato@hackconcursos.com.br', 'Email de contato'),
('plano_free_editais', '1', 'Número máximo de editais no plano free'),
('plano_basico_editais', '3', 'Número máximo de editais no plano básico');

-- Admin padrão (senha: Admin@123 - trocar após primeiro login!)
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`, `plano`) VALUES
('Administrador', 'admin@hackconcursos.com.br', '$2y$12$LqQg8NAUWYoZm1i5TkUMxu9q7kIZh2mNhK3cJGJ5mUZXsM5Ee7Sji', 'admin', 'premium');

SET foreign_key_checks = 1;
