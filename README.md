# 🚀 HackConcursos - Mentor Digital com IA

**HackConcursos** é uma plataforma de mentoria digital de alto desempenho para concurseiros, construída sob a filosofia de que aprovação não é apenas sobre *o quanto* você estuda, mas *como* você estuda.

O sistema utiliza Inteligência Artificial (Google Gemini/OpenAI) para transformar editais densos em planos de ação práticos, focando no estudo tático e na "leitura" da banca examinadora.

---

## 💎 Funcionalidades Principais

### 1. 📂 Processamento Inteligente de Editais
- **Upload de PDF**: Extração automática de disciplinas, pesos e tópicos programáticos.
- **Detecção de Prazos**: Identificação de datas de prova, banca e requisitos.

### 2. 📅 Plano de Estudo Intercalado (Interleaving)
- **Ciclos de Estudo**: Geração automática de cronogramas que intercalam disciplinas (round-robin) para evitar saturação mental e facilitar a fixação a longo prazo.
- **Ajuste de Carga Horária**: Divisão proporcional baseada em peso da matéria, dificuldade do aluno e urgência do edital.

### 3. 🤖 Mentor Hack IA
- **Mentoria Contextual**: Um assistente que conhece seu progresso real e sugere onde apertar o passo.
- **Tradutor de Juridiquês**: Explicação simplificada de conceitos complexos de matérias e termos do edital.
- **Sugestão de Estratégias**: Dicas de Pomodoro, Flashcards e Resumo Ativo sob demanda.

### 4. 📝 Simulados Profissionais
- **Geração de Questões**: Criação de questões inéditas no estilo específico da banca (FGV, FCC, CEBRASPE) usando IA.
- **Ranking de Desempenho**: Acompanhamento de acertos, erros e tempo por questão.

### 5. 📊 Diagnóstico e Dashboard
- **Análise de Nível**: Mapeamento inicial do conhecimento do aluno (Iniciante, Intermediário, Avançado).
- **Métricas Visuais**: Painéis Glassmorphism com progresso geral, metas diárias e horas líquidas estudadas.

### 6. 🔄 Gestão de Múltiplos Concursos
- **Swap de Planos**: Estude para mais de um concurso alternando o dashboard ativo sem perder dados de histórico.

---

## 🛠️ Stack Tecnológica
- **Backend**: PHP 8.x (Arquitetura Modular)
- **Database**: MySQL (Otimizado com chaves estrangeiras e índices)
- **Frontend**: Vanilla JS & CSS Premium (Foco em performance e estética Glassmorphism)
- **Acessibilidade**: Design Responsivo e Moderno
- **IA Core**: 
  - Google Gemini API (Análise de editais e Chat)
  - OpenAI API (Fallback e Complemento)

---

## 🏗️ Estrutura do Projeto
- `/aluno`: Dashboard e ferramentas do estudante.
- `/classes`: Lógica de negócio (StudyPlanner, GeminiService, etc).
- `/controllers`: Processamento de ações e rotas.
- `/config`: Configurações globais e banco de dados.
- `/includes`: Componentes de UI (Header, Sidebar, Footer).
- `/sql`: Schema completo e dumps de dados.

---

## 🔒 Segurança
- **.env Support**: Variáveis de ambiente isoladas para chaves de API e credenciais DB.
- **Proteção Local**: Sistema de login robusto e sanitização de dados.

---
*HackConcursos - Menos Conteúdo Repetitivo, Mais Estratégia de Aprovação.*
