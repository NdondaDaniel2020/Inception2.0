# ⚡ README - Interatividade JavaScript

## Visão Geral
O arquivo `styles_.js` adiciona toda a interatividade e dinamismo à landing page. Ele controla animações, navegação suave, revelação de elementos ao scroll e validação de formulário.

**Servidor Web:** httpd do busybox-extras em Alpine Linux 3.23  
**Porta:** 8888  
**Tecnologias:** JavaScript ES6+, Intersection Observer API, DOM Manipulation

## Estrutura do JavaScript

### 1. **NAVEGAÇÃO SUAVE (Smooth Scroll)**
```javascript
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
```

**O que faz:**
- `querySelectorAll('a[href^="#"]')`: Seleciona todos os links que começam com `#`
- `forEach(anchor => {...})`: Para cada link encontrado
- `addEventListener('click', ...)`: Escuta cliques
- `e.preventDefault()`: Impede o comportamento padrão (pulo instantâneo)
- `this.getAttribute('href')`: Pega o ID da seção (#inicio, #jornada, etc.)
- `querySelector(...)`: Encontra a seção correspondente
- `scrollIntoView({behavior: 'smooth'})`: Rola suavemente até a seção

**Exemplo de uso:**
```html
<a href="#skills">Skills</a>
```
Ao clicar, rola suavemente até `<section id="skills">`

**Por que é importante:**
- Melhora a experiência do usuário
- Navegação mais natural e agradável
- Mantém o usuário na mesma página

---

### 2. **REVELAÇÃO DE ELEMENTOS AO SCROLL**
```javascript
function reveal() {
    const reveals = document.querySelectorAll('.reveal');
    
    reveals.forEach(element => {
        const windowHeight = window.innerHeight;
        const elementTop = element.getBoundingClientRect().top;
        const elementVisible = 150;
        
        if (elementTop < windowHeight - elementVisible) {
            element.classList.add('active');
        }
    });
}

window.addEventListener('scroll', reveal);
reveal();
```

**Decompondo o código:**

**a) Seleção de elementos**
```javascript
const reveals = document.querySelectorAll('.reveal');
```
- Seleciona todos os elementos com classe `.reveal`
- Estes elementos começam invisíveis (CSS: `opacity: 0`)

**b) Medições**
```javascript
const windowHeight = window.innerHeight; // Altura da janela (viewport)
const elementTop = element.getBoundingClientRect().top; // Distância do elemento ao topo
const elementVisible = 150; // Pixels antes de ativar
```

**c) Lógica de revelação**
```javascript
if (elementTop < windowHeight - elementVisible) {
    element.classList.add('active');
}
```
- Se o elemento está a 150px de entrar na tela, revela
- Adiciona classe `active` que muda `opacity: 1` (CSS)

**d) Escuta de eventos**
```javascript
window.addEventListener('scroll', reveal);
reveal(); // Executa uma vez ao carregar
```
- Executa `reveal()` toda vez que a página é rolada
- Chama imediatamente para revelar elementos já visíveis

**Exemplo visual:**
```
┌─────────────────────┐  ← Topo da janela
│                     │
│  [Visível]          │
│                     │
│  [150px antes]  ←── Elemento começa a aparecer aqui
│                     │
│  [Ainda invisível]  │
│                     │
└─────────────────────┘  ← Base da janela
```

---

### 3. **ANIMAÇÃO DAS BARRAS DE HABILIDADES**
```javascript
function animateSkillBars() {
    const skillBars = document.querySelectorAll('.skill-progress');
    
    skillBars.forEach(bar => {
        const width = bar.style.width; // Ex: "95%"
        bar.style.width = '0%'; // Reseta para 0
        
        setTimeout(() => {
            bar.style.width = width; // Anima até a largura final
        }, 100);
    });
}
```

**O que faz:**
- Seleciona todas as barras de progresso
- Salva a largura final de cada barra (definida no HTML inline)
- Reseta para 0%
- Após 100ms, anima até a largura final

**Exemplo:**
```html
<div class="skill-progress" style="width: 95%"></div>
```
1. JS lê: `width = "95%"`
2. JS muda para: `style.width = "0%"`
3. Após 100ms: `style.width = "95%"`
4. CSS transição (`transition: width 1s`) cria animação suave

**Por que o setTimeout?**
- Sem delay, navegador não percebe a mudança de 0% → 95%
- 100ms dá tempo para o navegador processar

---

### 4. **OBSERVADOR DA SEÇÃO DE SKILLS (Intersection Observer)**
```javascript
const skillsSection = document.querySelector('.skills-section');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateSkillBars();
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.3 });

observer.observe(skillsSection);
```

**O que é Intersection Observer?**
- API moderna para detectar quando elemento entra na tela
- Mais eficiente que `scroll` event

**Decompondo:**

**a) Callback function**
```javascript
(entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) { // Se está visível
            animateSkillBars();
            observer.unobserve(entry.target); // Para de observar
        }
    });
}
```

**b) Opções**
```javascript
{ threshold: 0.3 }
```
- Ativa quando 30% da seção está visível

**c) Iniciar observação**
```javascript
observer.observe(skillsSection);
```
- Começa a observar a seção de skills

**Fluxo:**
1. Usuário rola a página
2. 30% da seção de skills entra na tela
3. Observer detecta e executa callback
4. `animateSkillBars()` é chamada
5. Observer para de observar (animação roda apenas 1x)

---

### 5. **ENVIO DO FORMULÁRIO DE CONTATO**
```javascript
document.querySelector('.contact-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    alert('Obrigado por entrar em contato! Em breve retornarei sua mensagem. 🚀');
    
    this.reset();
});
```

**O que faz:**

**a) Seleciona o formulário**
```javascript
document.querySelector('.contact-form')
```

**b) Escuta evento de submit**
```javascript
.addEventListener('submit', function(e) {...})
```
- Ativado ao clicar no botão ou pressionar Enter

**c) Previne envio padrão**
```javascript
e.preventDefault();
```
- Impede recarga da página
- Sem isso, página recarregaria e perderia estado

**d) Feedback ao usuário**
```javascript
alert('Obrigado por entrar em contato!...');
```
- Mostra mensagem de confirmação

**e) Limpa o formulário**
```javascript
this.reset();
```
- Limpa todos os campos
- `this` refere-se ao formulário

**Em produção real:**
- Substituir `alert()` por modal estilizado
- Adicionar validação de campos
- Enviar dados para servidor (AJAX/Fetch API)

---

### 6. **EFEITO PARALLAX NO HERO**
```javascript
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.hero');
    
    if (hero) {
        hero.style.transform = `translateY(${scrolled * 0.5}px)`;
    }
});
```

**O que é Parallax?**
- Elementos em camadas se movem em velocidades diferentes
- Cria sensação de profundidade

**Decompondo:**

**a) Detecta scroll**
```javascript
window.addEventListener('scroll', () => {...})
```

**b) Calcula quanto foi rolado**
```javascript
const scrolled = window.pageYOffset;
```
- `pageYOffset`: Pixels rolados verticalmente

**c) Move o hero mais devagar**
```javascript
hero.style.transform = `translateY(${scrolled * 0.5}px)`;
```
- Se rolou 100px, hero move apenas 50px (100 * 0.5)
- Se rolou 200px, hero move 100px
- Hero se move pela metade da velocidade do scroll

**Exemplo visual:**
```
Scroll:    0px  → 100px → 200px
Hero move: 0px  → 50px  → 100px  (metade da velocidade)
```

**Por que é legal?**
- Adiciona dinamismo
- Cria efeito de profundidade
- Hero "persiste" um pouco mais antes de sumir

---

## Conceitos JavaScript Utilizados

### 1. **Seletores DOM**
```javascript
document.querySelector('.classe')      // Primeiro elemento
document.querySelectorAll('.classe')   // Todos os elementos
```

### 2. **Event Listeners**
```javascript
elemento.addEventListener('evento', callback)
```
Eventos usados:
- `click`: Cliques
- `scroll`: Rolagem
- `submit`: Envio de formulário

### 3. **Arrow Functions**
```javascript
// Função tradicional
function minhaFuncao() { ... }

// Arrow function
const minhaFuncao = () => { ... }
```

### 4. **forEach Loop**
```javascript
array.forEach(item => {
    // Executa para cada item
});
```

### 5. **Template Literals**
```javascript
const nome = "Daniel";
console.log(`Olá, ${nome}!`); // Interpolação de variáveis
```

### 6. **Métodos de Array**
- `.forEach()`: Itera sobre elementos
- `.classList.add()`: Adiciona classe
- `.getAttribute()`: Pega atributo

### 7. **Manipulação do DOM**
```javascript
elemento.style.width = '50%';           // Muda CSS inline
elemento.classList.add('active');       // Adiciona classe
elemento.scrollIntoView({...});         // Rola até elemento
```

### 8. **Timers**
```javascript
setTimeout(() => {
    // Executa após delay
}, 100);
```

### 9. **Intersection Observer API**
- API moderna para detectar visibilidade
- Substitui scroll listeners ineficientes

---

## Fluxo de Interação Completo

### Quando a página carrega:
1. ✅ Navegação suave é configurada
2. ✅ Função `reveal()` executa imediatamente
3. ✅ Observer começa a monitorar seção de skills
4. ✅ Event listeners são registrados

### Quando usuário rola:
1. 🔄 `reveal()` é chamada
2. 🔄 Elementos `.reveal` aparecem se estão visíveis
3. 🔄 Parallax move o hero
4. 🔄 Observer verifica se skills está visível

### Quando usuário clica em link de navegação:
1. 🖱️ Evento de click é capturado
2. 🖱️ Comportamento padrão é prevenido
3. 🖱️ Scroll suave até a seção

### Quando seção de skills entra na tela:
1. 👀 Observer detecta (threshold: 30%)
2. 👀 `animateSkillBars()` é executada
3. 👀 Barras animam de 0% → largura final
4. 👀 Observer para de observar

### Quando formulário é enviado:
1. 📧 Evento de submit é capturado
2. 📧 Envio padrão é prevenido
3. 📧 Alert de confirmação aparece
4. 📧 Campos são limpos

---

## Performance e Boas Práticas

### ✅ Boas práticas usadas:
1. **Event Delegation**: Não anexa listener em cada link individualmente
2. **Unobserve**: Para de observar após animar (economiza recursos)
3. **Verificação de existência**: `if (hero)` antes de usar
4. **Timeout minimal**: 100ms é rápido mas funcional

### ⚠️ Possíveis melhorias:
1. **Throttle/Debounce** no scroll para melhor performance
2. **Passive listeners** para scroll (`{passive: true}`)
3. **RequestAnimationFrame** para parallax suave
4. **Lazy loading** de imagens
5. **Service Worker** para cache

---

## Interação HTML + CSS + JS

```
HTML                  CSS                    JavaScript
────────────────────────────────────────────────────────
<div class="reveal">  .reveal {              const reveals = 
                       opacity: 0;             querySelectorAll('.reveal');
                      }                       
                                              if (visible) {
                      .reveal.active {          element.classList.add('active');
                       opacity: 1;            }
                      }
```

1. **HTML** define estrutura e classes
2. **CSS** define estados visuais
3. **JavaScript** alterna entre estados baseado em interação

---

## Debugging e Testes

### Como testar cada função:

**1. Navegação suave:**
- Clique nos links do menu
- Observe rolagem suave

**2. Reveal ao scroll:**
- Role lentamente pela página
- Elementos devem aparecer gradualmente

**3. Barras de skills:**
- Role até seção de skills
- Barras devem animar de 0% → largura final

**4. Parallax:**
- Role para baixo
- Hero deve se mover mais devagar que o scroll

**5. Formulário:**
- Preencha campos
- Clique em "Enviar"
- Deve mostrar alert e limpar campos

### Console do navegador:
```javascript
// Testar seletores
console.log(document.querySelectorAll('.reveal').length);

// Testar scroll
console.log(window.pageYOffset);

// Forçar animação
animateSkillBars();
```

---

## Resumo

O JavaScript transforma uma página estática em uma **experiência interativa**:
- ✨ Animações suaves e responsivas
- 🎯 Feedback visual ao usuário
- 📱 Comportamento dinâmico
- 🚀 Performance otimizada

Sem JavaScript, a página funcionaria, mas seria **muito menos envolvente**!
