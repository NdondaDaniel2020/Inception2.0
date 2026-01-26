# 🎨 README - Estilização CSS

## Visão Geral
O arquivo `styles_.css` é responsável por toda a aparência visual da landing page. Ele transforma a estrutura HTML em uma experiência visual moderna, elegante e responsiva.

**Servidor Web:** httpd do busybox-extras em Alpine Linux 3.23  
**Porta:** 8888  
**Tecnologias:** CSS3, Custom Properties (variáveis CSS), Flexbox, Grid

## Estrutura do CSS

### 1. **Reset e Configuração Básica**
```css
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
```

**O que faz:**
- `*`: Seleciona TODOS os elementos
- `margin: 0; padding: 0;`: Remove espaçamentos padrão do navegador
- `box-sizing: border-box;`: Inclui padding e border no cálculo de largura/altura

### 2. **Variáveis CSS (Custom Properties)**
```css
:root {
    --primary-color: #6366f1;
    --secondary-color: #8b5cf6;
    --dark-bg: #0f172a;
    --card-bg: #1e293b;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --accent: #10b981;
}
```

**O que faz:**
- Define cores reutilizáveis em toda a página
- Facilita manutenção e criação de temas
- Uso: `background: var(--primary-color);`
- **Paleta de cores**:
  - Primary: Roxo/Azul (#6366f1)
  - Secondary: Roxo (#8b5cf6)
  - Dark BG: Azul escuro (#0f172a)
  - Card BG: Cinza escuro (#1e293b)
  - Accent: Verde (#10b981)

### 3. **Estilos Gerais do Body**
```css
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, var(--dark-bg) 0%, #1a1f3a 100%);
    color: var(--text-primary);
    line-height: 1.6;
    overflow-x: hidden;
}
```

**O que faz:**
- **font-family**: Define fontes com fallbacks
- **background**: Gradiente diagonal (135deg) do fundo escuro
- **color**: Cor padrão do texto
- **line-height**: Espaçamento entre linhas para legibilidade
- **overflow-x**: Previne scroll horizontal indesejado

### 4. **HEADER - Navegação Fixa**
```css
header {
    position: fixed;
    top: 0;
    width: 100%;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(10px);
    padding: 1rem 2rem;
    z-index: 1000;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
```

**O que faz:**
- `position: fixed`: Header fica fixo ao rolar
- `top: 0`: Posicionado no topo
- `rgba(15, 23, 42, 0.95)`: Fundo semi-transparente
- `backdrop-filter: blur(10px)`: Efeito de vidro fosco (glass morphism)
- `z-index: 1000`: Fica acima de outros elementos
- `box-shadow`: Sombra sutil

**Logo com Gradiente de Texto**
```css
.logo {
    font-size: 1.5rem;
    font-weight: bold;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
```
- Cria texto com gradiente (técnica avançada)
- `background-clip: text`: Aplica gradiente apenas no texto

### 5. **HERO SECTION - Layout em Grid**
```css
.hero {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    margin-top: 60px;
}

.hero-content {
    max-width: 1200px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
}
```

**O que faz:**
- `min-height: 100vh`: Ocupa altura total da viewport
- **Flexbox no hero**: Centraliza conteúdo vertical e horizontalmente
- **Grid no hero-content**: Duas colunas iguais (1fr 1fr)
- `gap: 4rem`: Espaçamento entre as colunas

**Título com Gradiente**
```css
.hero-text h1 {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, var(--primary-color), var(--accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
```
- Título grande (3.5rem) com gradiente roxo-verde

### 6. **Botões Estilizados**
```css
.btn {
    padding: 0.875rem 2rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
}
```

**O que faz:**
- **Btn base**: Estilo comum a todos os botões
- **Btn-primary**: Botão com gradiente
- **Hover**: Levanta 2px e adiciona sombra (feedback visual)
- `transition: all 0.3s`: Animação suave de 0.3 segundos

### 7. **Profile Card - Efeito de Card**
```css
.profile-card {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.profile-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary-color), var(--accent));
}
```

**O que faz:**
- Card com cantos arredondados e sombra profunda
- `::before`: Pseudo-elemento cria borda superior colorida
- `overflow: hidden`: Esconde elementos que ultrapassam o card

**Imagem de Perfil Circular**
```css
.profile-img {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    border: 5px solid var(--primary-color);
    margin-bottom: 1.5rem;
    object-fit: cover;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```
- `border-radius: 50%`: Cria círculo perfeito
- `object-fit: cover`: Imagem cobre área sem distorção
- Background gradiente como placeholder

### 8. **TIMELINE - Linha do Tempo Vertical**
```css
.timeline::before {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary-color), var(--accent));
}
```

**O que faz:**
- Pseudo-elemento cria linha vertical no centro
- `transform: translateX(-50%)`: Centraliza exatamente
- Gradiente vertical (180deg) roxo → verde

**Timeline Items Alternados**
```css
.timeline-item:nth-child(odd) {
    flex-direction: row;
}

.timeline-item:nth-child(even) {
    flex-direction: row-reverse;
}
```
- Items ímpares: conteúdo à esquerda
- Items pares: conteúdo à direita (efeito zigue-zague)

**Timeline Dot**
```css
.timeline-dot {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 20px;
    background: var(--accent);
    border-radius: 50%;
    border: 4px solid var(--dark-bg);
    z-index: 1;
}
```
- Círculo verde centralizado na linha
- Borda escura cria contraste

### 9. **SKILLS SECTION - Grid Responsivo**
```css
.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}
```

**O que faz:**
- `repeat(auto-fit, ...)`: Cria colunas automaticamente
- `minmax(280px, 1fr)`: Mínimo 280px, cresce para preencher espaço
- Responsivo sem media queries!

**Skill Card com Hover**
```css
.skill-card {
    background: var(--dark-bg);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    transition: all 0.3s;
    border: 2px solid transparent;
}

.skill-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-color);
}
```
- Hover levanta card e adiciona borda colorida

**Barra de Progresso**
```css
.skill-bar {
    background: var(--card-bg);
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.skill-progress {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-color), var(--accent));
    border-radius: 4px;
    transition: width 1s ease;
}
```
- Container cinza escuro
- Progress com gradiente horizontal
- `transition: width 1s`: Animação de 1 segundo (controlada por JS)

### 10. **PROJECTS SECTION - Cards de Projeto**
```css
.project-card {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    transition: all 0.3s;
}

.project-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3);
}
```
- Hover levanta 10px e aumenta sombra (efeito flutuante)

**Tags de Projeto**
```css
.tag {
    background: rgba(99, 102, 241, 0.2);
    color: var(--primary-color);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
}
```
- Pills arredondadas com fundo semi-transparente

### 11. **CONTACT SECTION - Formulário Estilizado**
```css
.form-group input,
.form-group textarea {
    padding: 1rem;
    border-radius: 8px;
    border: 2px solid transparent;
    background: var(--dark-bg);
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
}
```
- Inputs com fundo escuro
- Focus adiciona borda colorida (feedback visual)
- `outline: none`: Remove borda padrão do navegador

### 12. **ANIMAÇÕES**
```css
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.8s ease-out;
}
```
- Animação de entrada: aparece e sobe
- Aplicada ao hero section

**Reveal ao Scroll**
```css
.reveal {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s ease;
}

.reveal.active {
    opacity: 1;
    transform: translateY(0);
}
```
- Elementos começam invisíveis e deslocados
- JS adiciona classe `active` ao fazer scroll

### 13. **RESPONSIVIDADE - Mobile First**
```css
@media (max-width: 768px) {
    .hero-content {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .hero-text h1 {
        font-size: 2.5rem;
    }

    .nav-links {
        display: none;
    }

    .timeline::before {
        left: 20px;
    }

    .timeline-content {
        width: calc(100% - 60px);
        margin-left: 60px;
    }
}
```

**O que faz:**
- Aplica estilos apenas em telas ≤ 768px
- **Hero**: Grid vira 1 coluna
- **Título**: Reduz para 2.5rem
- **Menu**: Esconde (idealmente teria menu hamburguer)
- **Timeline**: Linha vai para esquerda, items ficam à direita

## Conceitos Avançados Usados

### 1. Flexbox
- Layout unidimensional (linha ou coluna)
- Usado em: navegação, botões, social links

### 2. CSS Grid
- Layout bidimensional (linhas E colunas)
- Usado em: hero content, skills grid, projects grid

### 3. Gradientes
- `linear-gradient(direção, cor1, cor2)`
- Usado em: backgrounds, textos, barras de progresso

### 4. Transformações
- `transform: translateY()`: Move verticalmente
- `transform: translateX()`: Move horizontalmente
- Usado em: hovers, animações

### 5. Transições
- `transition: propriedade duração timing-function`
- Cria animações suaves entre estados

### 6. Pseudo-elementos
- `::before`, `::after`: Criam elementos extras via CSS
- Usado em: linha da timeline, borda do profile card

### 7. Pseudo-classes
- `:hover`: Quando mouse passa por cima
- `:focus`: Quando elemento está focado
- `:nth-child(odd/even)`: Seleciona items pares/ímpares

### 8. Custom Properties (Variáveis)
- `--nome-variavel: valor`
- `var(--nome-variavel)`
- Facilita manutenção e temas

## Paleta de Cores e Tema

**Dark Mode Moderno:**
- Fundo: Gradiente azul escuro (#0f172a → #1a1f3a)
- Cards: Cinza escuro (#1e293b)
- Acentos: Roxo (#6366f1), Verde (#10b981)
- Texto: Cinza claro (#f1f5f9)

**Efeitos Visuais:**
- Glass morphism (backdrop-filter)
- Gradientes em textos
- Sombras profundas
- Hovers animados

## Responsividade

**Breakpoint:** 768px
- Desktop: Grid 2 colunas, menu horizontal
- Mobile: Grid 1 coluna, menu escondido

**Técnicas:**
- Grid auto-fit para skills
- Flexbox com wrap
- Unidades relativas (rem, %)
- Media queries
