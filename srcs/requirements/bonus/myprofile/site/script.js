// ===== NAVEGAÇÃO SUAVE =====
// Quando clicar em um link do menu, rola suavemente até a seção
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault(); // Impede o comportamento padrão do link
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth', // Animação suave
                block: 'start' // Alinha no topo da página
            });
        }
    });
});

// ===== REVELAÇÃO DE ELEMENTOS AO SCROLL =====
// Função que verifica se elementos estão visíveis na tela
function reveal() {
    const reveals = document.querySelectorAll('.reveal');
    
    reveals.forEach(element => {
        const windowHeight = window.innerHeight; // Altura da janela
        const elementTop = element.getBoundingClientRect().top; // Posição do elemento
        const elementVisible = 150; // Pixels antes de aparecer
        
        // Se o elemento está visível, adiciona a classe 'active'
        if (elementTop < windowHeight - elementVisible) {
            element.classList.add('active');
        }
    });
}

// Executa a função sempre que o usuário rolar a página
window.addEventListener('scroll', reveal);
reveal(); // Verifica elementos já visíveis ao carregar a página

// ===== ANIMAÇÃO DAS BARRAS DE HABILIDADES =====
// Anima as barras de progresso das skills
function animateSkillBars() {
    const skillBars = document.querySelectorAll('.skill-progress');
    
    skillBars.forEach(bar => {
        const width = bar.style.width; // Largura final da barra
        bar.style.width = '0%'; // Começa do zero
        
        // Depois de 100ms, anima até a largura final
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
}

// ===== OBSERVADOR DA SEÇÃO DE SKILLS =====
// Detecta quando a seção de skills aparece na tela
const skillsSection = document.querySelector('.skills-section');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            // Se a seção está visível, anima as barras
            animateSkillBars();
            observer.unobserve(entry.target); // Para de observar após animar
        }
    });
}, { threshold: 0.3 }); // Ativa quando 30% da seção está visível

observer.observe(skillsSection);

// ===== ENVIO DO FORMULÁRIO DE CONTATO =====
// Captura o envio do formulário
document.querySelector('.contact-form').addEventListener('submit', function(e) {
    e.preventDefault(); // Impede o envio padrão do formulário
    
    // Mostra mensagem de confirmação
    alert('Obrigado por entrar em contato! Em breve retornarei sua mensagem. 🚀');
    
    // Limpa todos os campos do formulário
    this.reset();
});

// ===== EFEITO PARALLAX NO HERO =====
// Cria um efeito de profundidade ao rolar a página
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset; // Quanto a página foi rolada
    const hero = document.querySelector('.hero');
    
    if (hero) {
        // Move o hero mais devagar que o scroll (efeito parallax)
        hero.style.transform = `translateY(${scrolled * 0.5}px)`;
    }
});
