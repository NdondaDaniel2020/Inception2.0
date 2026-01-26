# 📄 README - Estrutura HTML

## Visão Geral
O arquivo `index.html` é a estrutura principal da landing page, definindo todo o conteúdo semântico e hierarquia da página. Ele conta a história da jornada de Ndonda Daniel Matondo como desenvolvedor.

**Servidor Web:** httpd do busybox-extras em Alpine Linux 3.23  
**Porta:** 8888  
**Tipo:** Website estático (HTML, CSS, JavaScript)

## Estrutura do Documento

### 1. **HEAD - Metadados e Configuração**
```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="...">
    <title>Ndonda Daniel Matondo - Desenvolvedor Full Stack</title>
    <link rel="stylesheet" href="styles_.css">
</head>
```

**O que faz:**
- `charset="UTF-8"`: Define a codificação de caracteres para suportar acentos e caracteres especiais
- `viewport`: Garante responsividade em dispositivos móveis
- `description`: Melhora SEO (otimização para buscadores)
- `link rel="stylesheet"`: Conecta o arquivo CSS para estilização

### 2. **HEADER - Navegação Fixa**
```html
<header>
    <nav>
        <div class="logo">Ndonda Daniel</div>
        <ul class="nav-links">
            <li><a href="#inicio">Início</a></li>
            <li><a href="#jornada">Jornada</a></li>
            <!-- ... -->
        </ul>
    </nav>
</header>
```

**O que faz:**
- Barra de navegação fixa no topo da página
- Links com `href="#id"` permitem navegação suave para seções específicas
- Logo do desenvolvedor à esquerda
- Menu de navegação à direita

### 3. **HERO SECTION - Primeira Impressão**
```html
<section id="inicio" class="hero">
    <div class="hero-content fade-in-up">
        <div class="hero-text">
            <h1>Olá, eu sou Ndonda Daniel 👋</h1>
            <p class="subtitle">Desenvolvedor Full Stack | 42 Student</p>
            <p>Uma jornada que começou em 2020...</p>
            <div class="cta-buttons">
                <a href="#projetos" class="btn btn-primary">Ver Projetos</a>
                <a href="#contato" class="btn btn-secondary">Entre em Contato</a>
            </div>
        </div>
        <div class="hero-image">
            <div class="profile-card">
                <div class="profile-img"></div>
                <h3>Ndonda Daniel Matondo</h3>
                <!-- ... -->
            </div>
        </div>
    </div>
</section>
```

**O que faz:**
- **Hero-text**: Título principal, subtítulo e introdução da história
- **CTA buttons**: Botões de chamada para ação (Call To Action)
- **Hero-image**: Card de perfil com foto estilizada
- **Social links**: Links para redes sociais (GitHub, LinkedIn, etc.)
- Classe `fade-in-up`: Prepara elemento para animação de entrada

### 4. **TIMELINE SECTION - História em Linha do Tempo**
```html
<section id="jornada" class="timeline-section">
    <h2 class="section-title">Minha Jornada 🚀</h2>
    <div class="timeline">
        <div class="timeline-item reveal">
            <div class="timeline-content">
                <div class="timeline-year">2020 - O Início</div>
                <h3 class="timeline-title">Descobrindo a Programação</h3>
                <p class="timeline-description">...</p>
            </div>
            <div class="timeline-dot"></div>
        </div>
        <!-- Mais items da timeline para cada ano -->
    </div>
</section>
```

**O que faz:**
- Conta a história cronológica do desenvolvedor
- **Timeline-item**: Cada marco importante da jornada
- **Timeline-dot**: Ponto visual na linha do tempo
- **Timeline-year**: Ano do evento
- **Reveal class**: Elementos aparecem ao fazer scroll
- **Estrutura dos eventos**:
  - **2020**: Descoberta da programação (Gustavo Guanabara)
  - **2021**: Python, POO, Redes Neurais, SQL
  - **2022**: Web development (HTML, CSS, JS, React)
  - **2023**: Aperfeiçoamento e boas práticas
  - **2024**: 42 Luanda - Projetos em C
  - **2025-2026**: Full Stack, FastAPI, Docker

### 5. **SKILLS SECTION - Habilidades Técnicas**
```html
<section id="skills" class="skills-section">
    <div class="skills-container">
        <h2 class="section-title">Minhas Skills 💻</h2>
        <div class="skills-grid">
            <div class="skill-card reveal">
                <div class="skill-icon">🐍</div>
                <h3 class="skill-name">Python</h3>
                <div class="skill-bar">
                    <div class="skill-progress" style="width: 95%"></div>
                </div>
                <p class="skill-percentage">Avançado</p>
            </div>
            <!-- Mais skill cards -->
        </div>
    </div>
</section>
```

**O que faz:**
- Grid responsivo de cards de habilidades
- **Skill-icon**: Emoji representando a tecnologia
- **Skill-bar**: Barra de progresso visual
- **Skill-progress**: Largura inline define o nível (animado por JS)
- **Skills incluídas**:
  - Linguagens: Python, C, C++, JavaScript
  - Frontend: HTML/CSS, React
  - Backend: FastAPI
  - Databases: MySQL, PostgreSQL, SQLite, Redis
  - DevOps: Docker
  - Especialidades: OpenCV, Redes Neurais, ML

### 6. **PROJECTS SECTION - Portfólio**
```html
<section id="projetos" class="projects-section">
    <h2 class="section-title">Projetos em Destaque 🎯</h2>
    <div class="projects-grid">
        <div class="project-card reveal">
            <div class="project-image">🎮</div>
            <div class="project-content">
                <h3 class="project-title">Cub3D - Raycaster 3D</h3>
                <p class="project-description">...</p>
                <div class="project-tags">
                    <span class="tag">C</span>
                    <span class="tag">Gráficos</span>
                    <!-- ... -->
                </div>
            </div>
        </div>
        <!-- Mais project cards -->
    </div>
</section>
```

**O que faz:**
- Grid de cards de projetos
- **Project-image**: Área visual com gradiente e emoji
- **Project-title**: Nome do projeto
- **Project-description**: Descrição técnica
- **Project-tags**: Tags de tecnologias usadas
- **Projetos destacados**:
  1. **Cub3D**: Raycaster 3D em C (42 School)
  2. **Minishell**: Shell customizado (42 School)
  3. **Flappy Bird IA**: Aprendizado por reforço
  4. **Philosophers**: Concorrência e threads (42 School)
  5. **Sistemas Desktop**: PyQt5 + SQL
  6. **FastAPI Server**: Backend com Docker

### 7. **CONTACT SECTION - Formulário de Contato**
```html
<section id="contato" class="contact-section">
    <div class="contact-container">
        <h2 class="section-title">Vamos Conversar? 💬</h2>
        <form class="contact-form">
            <div class="form-group">
                <label for="name">Nome</label>
                <input type="text" id="name" name="name" placeholder="..." required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="..." required>
            </div>
            <div class="form-group">
                <label for="message">Mensagem</label>
                <textarea id="message" name="message" placeholder="..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enviar Mensagem</button>
        </form>
    </div>
</section>
```

**O que faz:**
- Formulário de contato completo
- **Form-group**: Agrupa label + input
- **Input types**: `text`, `email`, `textarea`
- **Required**: Validação HTML5 nativa
- **Placeholder**: Texto de exemplo nos campos
- **Submit button**: Envia o formulário (tratado pelo JS)

### 8. **FOOTER - Rodapé**
```html
<footer>
    <p>&copy; 2026 Ndonda Daniel Matondo. Desenvolvido com ❤️ em Luanda, Angola</p>
    <p style="margin-top: 0.5rem; font-size: 0.9rem;">
        "Sou movido por curiosidade, resolução de problemas..."
    </p>
</footer>
```

**O que faz:**
- Copyright e informações finais
- Citação inspiracional
- Fecha a página com estilo

### 9. **SCRIPT - Conexão com JavaScript**
```html
<script src="styles_.js"></script>
```

**O que faz:**
- Carrega o arquivo JavaScript
- Adiciona interatividade (scroll suave, animações, etc.)

## Conceitos Importantes

### Semântica HTML5
- `<header>`: Cabeçalho da página
- `<nav>`: Navegação
- `<section>`: Seções temáticas
- `<footer>`: Rodapé
- `<form>`: Formulários interativos

### IDs e Classes
- **IDs** (`id="inicio"`): Únicos, usados para navegação e JS
- **Classes** (`class="hero"`): Reutilizáveis, usadas para CSS

### Acessibilidade
- `alt` em imagens (quando aplicável)
- `title` em links sociais
- Labels associados a inputs
- Estrutura hierárquica de headings (h1, h2, h3)

### Responsividade
- Meta viewport configurado
- Estrutura flexível preparada para CSS Grid/Flexbox
- Conteúdo adaptável a diferentes tamanhos de tela

## Como Funciona em Conjunto

1. **HTML** define a estrutura e conteúdo
2. **CSS** (`styles_.css`) estiliza visualmente
3. **JavaScript** (`styles_.js`) adiciona interatividade

O HTML é o "esqueleto" da página - sem ele, não há conteúdo para estilizar ou tornar interativo!
