/**
 * HackConcursos Onboarding Logic
 * Uses Driver.js for guided tours
 */

function startOnboarding() {
    const driver = window.driver.js.driver;

    const allSteps = [
        {
            element: '#hud-stats',
            popover: {
                title: 'Seu Status em Tempo Real',
                description: 'Aqui você monitora seu Nível, Streak de estudos e sua Energia (Tokens) disponível para usar a IA.',
                side: "bottom",
                align: 'start'
            }
        },
        {
            element: '#current-mission',
            popover: {
                title: 'Sua Próxima Batalha',
                description: 'O sistema analisa seu edital e desempenho para gerar a missão mais importante para sua aprovação hoje.',
                side: "bottom",
                align: 'center'
            }
        },
        {
            element: '#btn-activate-ia',
            popover: {
                title: 'Poder de Fogo',
                description: 'Use suas Skills de IA para obter diagnósticos profundos e resolver gargalos de aprendizado rapidamente.',
                side: "left",
                align: 'center'
            }
        },
        {
            element: '#sidebar',
            popover: {
                title: 'Seu Arsenal',
                description: 'Navegue entre simulados, plano de estudos e o chat direto com o Consultor IA.',
                side: "right",
                align: 'center'
            }
        },
        {
            popover: {
                title: 'Pronto para a Guerra?',
                description: 'Agora é com você. Cumpra suas missões diárias e acompanhe sua evolução até a posse!',
            }
        }
    ];

    // Filtrar passos cujos elementos não existem na página atual (exceto o último que não tem elemento)
    const activeSteps = allSteps.filter(step => {
        if (!step.element) return true;
        return document.querySelector(step.element) !== null;
    });

    const tour = driver({
        showProgress: true,
        animate: true,
        allowClose: false,
        overlayColor: '#000',
        overlayOpacity: 0.85,
        nextBtnText: 'Próximo —>',
        prevBtnText: '<— Voltar',
        doneBtnText: 'Entendido, soldado!',
        steps: activeSteps,
        onDestroyStarted: () => {
            const activeIndex = tour.getActiveIndex();
            const stepsCount = activeSteps.length;

            if (activeIndex < stepsCount - 1) {
                tour.destroy();
                return;
            }
            
            // Marcar onboarding como visto via AJAX
            const url = (window.HC_APP_URL || '..') + '/controllers/mark_onboarding.php';
            fetch(url, { method: 'POST' })
                .catch(err => console.error("Erro ao marcar onboarding:", err));
            
            tour.destroy();
        }
    });

    tour.drive();
}

// Iniciar se o elemento 'start-onboarding' estiver presente ou via trigger PHP
document.addEventListener('DOMContentLoaded', () => {
    const shouldStart = document.body.dataset.onboarding === 'true';
    if (shouldStart) {
        setTimeout(startOnboarding, 1000);
    }
});
