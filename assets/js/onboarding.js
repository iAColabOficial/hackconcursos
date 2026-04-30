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
                title: 'Status do Sistema',
                description: 'Aqui você monitora seu Domínio, Sequência de estudos e as Cargas de Energia disponíveis para usar a IA.',
                side: "bottom",
                align: 'start'
            }
        },
        {
            element: '#current-mission',
            popover: {
                title: 'Seu Próximo Foco',
                description: 'O sistema analisa seu edital e desempenho para identificar o ponto de maior retorno para sua evolução hoje.',
                side: "bottom",
                align: 'center'
            }
        },
        {
            element: '#btn-activate-ia',
            popover: {
                title: 'Capacidade de Análise',
                description: 'Use as ferramentas de IA para obter diagnósticos profundos e remover gargalos de aprendizado rapidamente.',
                side: "left",
                align: 'center'
            }
        },
        {
            element: '#sidebar',
            popover: {
                title: 'Suas Ferramentas',
                description: 'Navegue entre o caminho de evolução, simulados e o chat direto com o Consultor IA.',
                side: "right",
                align: 'center'
            }
        },
        {
            popover: {
                title: 'Pronto para Otimizar?',
                description: 'Agora é com você. Cumpra seus sprints diários e acompanhe sua evolução até a posse!',
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
        doneBtnText: 'Entendido, Mastermind!',
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
