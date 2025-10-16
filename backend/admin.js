/**
 * ============================================
 * PAINEL ADMINISTRATIVO - JUNTAS GOIÁS
 * JavaScript Principal
 * ============================================
 * 
 * Este arquivo contém toda a lógica do painel administrativo:
 * - Sistema de autenticação
 * - Navegação entre seções
 * - Carregamento de dados via API
 * - Gerenciamento de demandas, serviços, imagens e banners
 * - Gráficos e estatísticas
 */

// ============================================
// CONFIGURAÇÕES GLOBAIS
// ============================================

const API_BASE = './backend/'; // URL base das APIs

// Estado global da aplicação
const AppState = {
    usuarioLogado: null,
    secaoAtual: 'dashboard',
    demandas: [],
    servicos: [],
    imagens: []
};

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Painel Administrativo - Inicializando...');
    
    // Verifica se há sessão ativa
    verificarSessao();
    
    // Configura event listeners
    configurarEventListeners();
    
    // Atualiza relógio
    atualizarRelogio();
    setInterval(atualizarRelogio, 1000);
});

// ============================================
// AUTENTICAÇÃO
// ============================================

/**
 * Verifica se há uma sessão ativa
 */
async function verificarSessao() {
    try {
        const response = await fetch(API_BASE + 'login.php?action=check');
        const data = await response.json();
        
        if (data.logado) {
            // Usuário está logado
            AppState.usuarioLogado = data.admin;
            mostrarDashboard();
            carregarDadosIniciais();
        } else {
            // Usuário não está logado
            mostrarLogin();
        }
    } catch (error) {
        console.error('Erro ao verificar sessão:', error);
        mostrarLogin();
    }
}

/**
 * Processa o formulário de login
 */
document.getElementById('login-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const usuario = document.getElementById('usuario').value;
    const senha = document.getElementById('senha').value;
    const btnLogin = document.getElementById('btn-login');
    const btnText = document.getElementById('btn-login-text');
    const btnLoading = document.getElementById('btn-login-loading');
    const errorDiv = document.getElementById('login-error');
    
    // Mostra loading
    btnLogin.disabled = true;
    btnText.textContent = 'Entrando...';
    btnLoading.classList.remove('hidden');
    errorDiv.classList.add('hidden');
    
    try {
        const response = await fetch(API_BASE + 'login.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ usuario, senha })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            // Login bem-sucedido
            AppState.usuarioLogado = data.admin;
            mostrarDashboard();
            carregarDadosIniciais();
        } else {
            // Erro no login
            mostrarErroLogin(data.mensagem || 'Erro ao fazer login');
        }
    } catch (error) {
        console.error('Erro ao fazer login:', error);
        mostrarErroLogin('Erro de conexão. Tente novamente.');
    } finally {
        // Remove loading
        btnLogin.disabled = false;
        btnText.textContent = 'Entrar';
        btnLoading.classList.add('hidden');
    }
});

/**
 * Mostra mensagem de erro no login
 */
function mostrarErroLogin(mensagem) {
    const errorDiv = document.getElementById('login-error');
    const errorText = document.getElementById('login-error-text');
    
    errorText.textContent = mensagem;
    errorDiv.classList.remove('hidden');
}

/**
 * Faz logout do sistema
 */
async function fazerLogout() {
    try {
        await fetch(API_BASE + 'login.php?action=logout', {
            method: 'POST'
        });
        
        AppState.usuarioLogado = null;
        mostrarLogin();
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
    }
}

// ============================================
// NAVEGAÇÃO E INTERFACE
// ============================================

/**
 * Mostra a tela de login
 */
function mostrarLogin() {
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('dashboard-screen').classList.add('hidden');
}

/**
 * Mostra o dashboard
 */
function mostrarDashboard() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('dashboard-screen').classList.remove('hidden');
    
    // Atualiza informações do usuário
    if (AppState.usuarioLogado) {
        const inicial = AppState.usuarioLogado.nome.charAt(0).toUpperCase();
        document.getElementById('user-initial').textContent = inicial;
        document.getElementById('user-name').textContent = AppState.usuarioLogado.nome;
    }
    
    // Navega para o dashboard
    navegarParaSecao('dashboard');
}

/**
 * Navega para uma seção específica
 */
function navegarParaSecao(secao) {
    console.log('Navegando para:', secao);
    
    // Remove classe active de todos os links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active', 'bg-gray-800');
    });
    
    // Adiciona classe active no link atual
    const linkAtivo = document.querySelector(`[data-section="${secao}"]`);
    if (linkAtivo) {
        linkAtivo.classList.add('active', 'bg-gray-800');
    }
    
    // Esconde todas as seções
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Mostra a seção atual
    const secaoElement = document.getElementById(`section-${secao}`);
    if (secaoElement) {
        secaoElement.classList.remove('hidden');
    }
    
    // Atualiza título da página
    const titulos = {
        'dashboard': ['Dashboard', 'Visão geral do sistema'],
        'demandas': ['Demandas', 'Gerenciar mensagens e solicitações'],
        'servicos': ['Serviços', 'Gerenciar serviços do site'],
        'banners': ['Banners', 'Gerenciar banners do carrossel'],
        'imagens': ['Imagens', 'Galeria de imagens']
    };
    
    if (titulos[secao]) {
        document.getElementById('page-title').textContent = titulos[secao][0];
        document.getElementById('page-subtitle').textContent = titulos[secao][1];
    }
    
    // Atualiza estado
    AppState.secaoAtual = secao;
    
    // Carrega dados da seção
    carregarDadosSecao(secao);
}

/**
 * Configura todos os event listeners
 */
function configurarEventListeners() {
    // Navegação do menu
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const secao = this.dataset.section;
            navegarParaSecao(secao);
        });
    });
    
    // Botão de logout
    document.getElementById('btn-logout')?.addEventListener('click', fazerLogout);
    
    // Botão de refresh
    document.getElementById('btn-refresh')?.addEventListener('click', function() {
        carregarDadosSecao(AppState.secaoAtual);
    });
    
    // Toggle sidebar
    document.getElementById('btn-toggle-sidebar')?.addEventListener('click', toggleSidebar);
    
    // Filtros de demandas
    document.getElementById('btn-filtrar-demandas')?.addEventListener('click', filtrarDemandas);
    
    // Novo serviço
    document.getElementById('btn-novo-servico')?.addEventListener('click', function() {
        abrirModalServico();
    });
    
    // Form de serviço
    document.getElementById('form-servico')?.addEventListener('submit', salvarServico);
    
    // Upload de banners
    document.getElementById('upload-banner1')?.addEventListener('change', function() {
        fazerUploadBanner(1, this.files[0]);
    });
    document.getElementById('upload-banner2')?.addEventListener('change', function() {
        fazerUploadBanner(2, this.files[0]);
    });
    document.getElementById('upload-banner3')?.addEventListener('change', function() {
        fazerUploadBanner(3, this.files[0]);
    });
    
    // Upload de imagens
    document.getElementById('btn-upload-imagem')?.addEventListener('click', function() {
        document.getElementById('input-upload-imagem').click();
    });
    
    document.getElementById('input-upload-imagem')?.addEventListener('change', function() {
        if (this.files[0]) {
            fazerUploadImagem(this.files[0]);
        }
    });
}

/**
 * Toggle do sidebar (expandir/recolher)
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    sidebar.classList.toggle('sidebar-collapsed');
    
    if (sidebar.classList.contains('sidebar-collapsed')) {
        mainContent.style.marginLeft = '80px';
        document.querySelectorAll('.nav-text').forEach(el => el.classList.add('hidden'));
        document.getElementById('sidebar-title').classList.add('hidden');
    } else {
        mainContent.style.marginLeft = '16rem';
        document.querySelectorAll('.nav-text').forEach(el => el.classList.remove('hidden'));
        document.getElementById('sidebar-title').classList.remove('hidden');
    }
}

/**
 * Atualiza o relógio
 */
function atualizarRelogio() {
    const agora = new Date();
    const horas = String(agora.getHours()).padStart(2, '0');
    const minutos = String(agora.getMinutes()).padStart(2, '0');
    const segundos = String(agora.getSeconds()).padStart(2, '0');
    
    const elementoRelogio = document.getElementById('current-time');
    if (elementoRelogio) {
        elementoRelogio.textContent = `${horas}:${minutos}:${segundos}`;
    }
}

// ============================================
// CARREGAMENTO DE DADOS
// ============================================

/**
 * Carrega dados iniciais do sistema
 */
async function carregarDadosIniciais() {
    console.log('Carregando dados iniciais...');
    carregarDadosSecao('dashboard');
}

/**
 * Carrega dados de uma seção específica
 */
async function carregarDadosSecao(secao) {
    console.log('Carregando dados da seção:', secao);
    
    switch(secao) {
        case 'dashboard':
            await carregarDashboard();
            break;
        case 'demandas':
            await carregarDemandas();
            break;
        case 'servicos':
            await carregarServicos();
            break;
        case 'banners':
            // Banners já são exibidos por padrão
            break;
        case 'imagens':
            await carregarImagens();
            break;
    }
}

/**
 * Carrega dados do dashboard
 */
async function carregarDashboard() {
    try {
        // Carrega resumo estatístico
        const responseResumo = await fetch(API_BASE + 'dashboard_api.php?action=resumo');
        const dataResumo = await responseResumo.json();
        
        if (dataResumo.sucesso) {
            const resumo = dataResumo.resumo;
            
            // Atualiza cards de estatísticas
            document.getElementById('stat-total-demandas').textContent = resumo.demandas.total || 0;
            document.getElementById('stat-pendentes').textContent = resumo.demandas.pendentes || 0;
            document.getElementById('stat-em-andamento').textContent = resumo.demandas.em_andamento || 0;
            document.getElementById('stat-resolvidos').textContent = resumo.demandas.resolvidos || 0;
            document.getElementById('stat-demandas-hoje').textContent = `+${resumo.demandas.hoje || 0} hoje`;
            
            // Atualiza badge de notificações
            const badge = document.getElementById('badge-demandas');
            if (resumo.demandas.pendentes > 0) {
                badge.textContent = resumo.demandas.pendentes;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
        
        // Carrega demandas recentes
        const responseDemandas = await fetch(API_BASE + 'dashboard_api.php?action=demandas_recentes&limite=5');
        const dataDemandas = await responseDemandas.json();
        
        if (dataDemandas.sucesso) {
            renderizarDemandasRecentes(dataDemandas.demandas);
        }
        
        // Carrega dados para gráficos
        await carregarGraficos();
        
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        mostrarErro('Erro ao carregar dados do dashboard');
    }
}

/**
 * Renderiza a lista de demandas recentes no dashboard
 */
function renderizarDemandasRecentes(demandas) {
    const container = document.getElementById('lista-demandas-recentes');
    
    if (demandas.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-inbox text-3xl mb-2"></i>
                <p>Nenhuma demanda encontrada</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = demandas.map(demanda => `
        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <div class="flex-1">
                <div class="flex items-center space-x-3">
                    <span class="status-badge status-${demanda.status}">${traduzirStatus(demanda.status)}</span>
                    <h4 class="font-semibold text-gray-900">${escapeHtml(demanda.nome)}</h4>
                </div>
                <p class="text-sm text-gray-600 mt-1">${escapeHtml(demanda.assunto)}</p>
                <p class="text-xs text-gray-400 mt-1">
                    <i class="far fa-clock mr-1"></i>
                    ${demanda.tempo_decorrido}
                </p>
            </div>
            <button onclick="visualizarDemanda(${demanda.id})" class="ml-4 px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors">
                <i class="fas fa-eye mr-2"></i>
                Ver
            </button>
        </div>
    `).join('');
}

/**
 * Carrega gráficos do dashboard
 */
async function carregarGraficos() {
    try {
        // Gráfico de demandas por dia
        const responseDia = await fetch(API_BASE + 'dashboard_api.php?action=grafico_demandas&tipo=semana');
        const dataDia = await responseDia.json();
        
        if (dataDia.sucesso) {
            renderizarGraficoDemandas(dataDia.dados);
        }
        
        // Gráfico de status
        const responseResumo = await fetch(API_BASE + 'dashboard_api.php?action=resumo');
        const dataResumo = await responseResumo.json();
        
        if (dataResumo.sucesso) {
            renderizarGraficoStatus(dataResumo.resumo.demandas);
        }
        
    } catch (error) {
        console.error('Erro ao carregar gráficos:', error);
    }
}

/**
 * Renderiza gráfico de demandas por dia
 */
function renderizarGraficoDemandas(dados) {
    const ctx = document.getElementById('chart-demandas-dia');
    if (!ctx) return;
    
    // Destrói gráfico anterior se existir
    if (window.chartDemandas) {
        window.chartDemandas.destroy();
    }
    
    const labels = dados.map(d => {
        const date = new Date(d.data || d.dia_semana);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const valores = dados.map(d => d.total);
    
    window.chartDemandas = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Demandas',
                data: valores,
                borderColor: '#ec4899',
                backgroundColor: 'rgba(236, 72, 153, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

/**
 * Renderiza gráfico de status
 */
function renderizarGraficoStatus(dados) {
    const ctx = document.getElementById('chart-status');
    if (!ctx) return;
    
    // Destrói gráfico anterior se existir
    if (window.chartStatus) {
        window.chartStatus.destroy();
    }
    
    window.chartStatus = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pendentes', 'Em Andamento', 'Resolvidos'],
            datasets: [{
                data: [
                    dados.pendentes || 0,
                    dados.em_andamento || 0,
                    dados.resolvidos || 0
                ],
                backgroundColor: [
                    '#fbbf24',
                    '#3b82f6',
                    '#10b981'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// ============================================
// GERENCIAMENTO DE DEMANDAS
// ============================================

/**
 * Carrega lista de demandas
 */
async function carregarDemandas() {
    try {
        const tabela = document.getElementById('tabela-demandas');
        tabela.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <p>Carregando demandas...</p>
                </td>
            </tr>
        `;
        
        const response = await fetch(API_BASE + 'demandas_admin_api.php?action=listar&limite=50');
        const data = await response.json();
        
        if (data.sucesso) {
            AppState.demandas = data.demandas;
            renderizarTabelaDemandas(data.demandas);
        }
    } catch (error) {
        console.error('Erro ao carregar demandas:', error);
        mostrarErro('Erro ao carregar demandas');
    }
}

/**
 * Filtra demandas
 */
async function filtrarDemandas() {
    const status = document.getElementById('filtro-status').value;
    const lida = document.getElementById('filtro-lida').value;
    const busca = document.getElementById('filtro-busca').value;
    
    let url = API_BASE + 'demandas_admin_api.php?action=listar&limite=50';
    
    if (status) url += `&status=${status}`;
    if (lida) url += `&lida=${lida}`;
    if (busca) url += `&busca=${encodeURIComponent(busca)}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.sucesso) {
            renderizarTabelaDemandas(data.demandas);
        }
    } catch (error) {
        console.error('Erro ao filtrar demandas:', error);
    }
}

/**
 * Renderiza tabela de demandas
 */
function renderizarTabelaDemandas(demandas) {
    const tabela = document.getElementById('tabela-demandas');
    
    if (demandas.length === 0) {
        tabela.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>Nenhuma demanda encontrada</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tabela.innerHTML = demandas.map(demanda => `
        <tr class="${demanda.lida == 0 ? 'bg-blue-50' : ''} hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4">
                <span class="status-badge status-${demanda.status}">${traduzirStatus(demanda.status)}</span>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center">
                    ${demanda.lida == 0 ? '<span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>' : ''}
                    <span class="font-medium text-gray-900">${escapeHtml(demanda.nome)}</span>
                </div>
                <div class="text-sm text-gray-500">${escapeHtml(demanda.email)}</div>
            </td>
            <td class="px-6 py-4 text-gray-900">${escapeHtml(demanda.assunto)}</td>
            <td class="px-6 py-4 text-sm text-gray-500">
                ${formatarData(demanda.data_envio)}
            </td>
            <td class="px-6 py-4">
                <button onclick="visualizarDemanda(${demanda.id})" class="px-3 py-1 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors text-sm">
                    <i class="fas fa-eye mr-1"></i>
                    Ver
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Visualiza detalhes de uma demanda
 */
async function visualizarDemanda(id) {
    try {
        const response = await fetch(API_BASE + `demandas_admin_api.php?action=buscar&id=${id}`);
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarModalDemanda(data.demanda);
        }
    } catch (error) {
        console.error('Erro ao buscar demanda:', error);
    }
}

// Continuação do admin.js (Parte 2)

/**
 * Mostra modal com detalhes da demanda
 */
function mostrarModalDemanda(demanda) {
    const modal = document.getElementById('modal-demanda');
    const conteudo = document.getElementById('modal-demanda-conteudo');
    
    conteudo.innerHTML = `
        <div class="space-y-6">
            <!-- Status e Prioridade -->
            <div class="flex items-center space-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="demanda-status-${demanda.id}" class="px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="pendente" ${demanda.status === 'pendente' ? 'selected' : ''}>Pendente</option>
                        <option value="em_andamento" ${demanda.status === 'em_andamento' ? 'selected' : ''}>Em Andamento</option>
                        <option value="resolvido" ${demanda.status === 'resolvido' ? 'selected' : ''}>Resolvido</option>
                        <option value="cancelado" ${demanda.status === 'cancelado' ? 'selected' : ''}>Cancelado</option>
                    </select>
                </div>
                <button onclick="atualizarStatusDemanda(${demanda.id})" class="mt-6 px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700">
                    Atualizar Status
                </button>
            </div>
            
            <!-- Informações do Solicitante -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-bold text-gray-900 mb-3">Informações do Solicitante</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Nome</p>
                        <p class="font-medium text-gray-900">${escapeHtml(demanda.nome)}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-medium text-gray-900">${escapeHtml(demanda.email)}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Telefone</p>
                        <p class="font-medium text-gray-900">${escapeHtml(demanda.telefone || 'Não informado')}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Data de Envio</p>
                        <p class="font-medium text-gray-900">${formatarData(demanda.data_envio)}</p>
                    </div>
                </div>
            </div>
            
            <!-- Assunto -->
            <div>
                <h4 class="font-bold text-gray-900 mb-2">Assunto</h4>
                <p class="text-gray-700">${escapeHtml(demanda.assunto)}</p>
            </div>
            
            <!-- Mensagem -->
            <div>
                <h4 class="font-bold text-gray-900 mb-2">Mensagem</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-gray-700 whitespace-pre-wrap">${escapeHtml(demanda.mensagem)}</p>
                </div>
            </div>
            
            <!-- Notas Internas -->
            <div>
                <h4 class="font-bold text-gray-900 mb-2">Notas Internas</h4>
                <div class="bg-yellow-50 p-4 rounded-lg mb-3 max-h-40 overflow-y-auto">
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">${demanda.notas_internas || 'Nenhuma nota adicionada'}</p>
                </div>
                <div class="flex space-x-2">
                    <input 
                        type="text" 
                        id="nova-nota-${demanda.id}" 
                        placeholder="Adicionar nova nota..." 
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg">
                    <button onclick="adicionarNota(${demanda.id})" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="flex space-x-3 pt-4 border-t border-gray-200">
                <button onclick="deletarDemanda(${demanda.id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-trash mr-2"></i>
                    Deletar
                </button>
                <button onclick="fecharModal('modal-demanda')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Fechar
                </button>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
}

/**
 * Atualiza o status de uma demanda
 */
async function atualizarStatusDemanda(id) {
    const novoStatus = document.getElementById(`demanda-status-${id}`).value;
    
    try {
        const response = await fetch(API_BASE + 'demandas_admin_api.php?action=atualizar_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status: novoStatus })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Status atualizado com sucesso!');
            carregarDemandas();
            fecharModal('modal-demanda');
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao atualizar status:', error);
        mostrarErro('Erro ao atualizar status');
    }
}

/**
 * Adiciona nota interna a uma demanda
 */
async function adicionarNota(id) {
    const inputNota = document.getElementById(`nova-nota-${id}`);
    const nota = inputNota.value.trim();
    
    if (!nota) {
        mostrarErro('Digite uma nota primeiro');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'demandas_admin_api.php?action=adicionar_nota', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, nota })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Nota adicionada!');
            inputNota.value = '';
            visualizarDemanda(id); // Recarrega a demanda
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao adicionar nota:', error);
        mostrarErro('Erro ao adicionar nota');
    }
}

/**
 * Deleta uma demanda
 */
async function deletarDemanda(id) {
    if (!confirm('Tem certeza que deseja deletar esta demanda?')) {
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'demandas_admin_api.php?action=deletar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Demanda deletada com sucesso!');
            carregarDemandas();
            fecharModal('modal-demanda');
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao deletar demanda:', error);
        mostrarErro('Erro ao deletar demanda');
    }
}

// ============================================
// GERENCIAMENTO DE SERVIÇOS
// ============================================

/**
 * Carrega lista de serviços
 */
async function carregarServicos() {
    try {
        const container = document.getElementById('lista-servicos');
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Carregando serviços...</p>
            </div>
        `;
        
        const response = await fetch(API_BASE + 'servicos_api.php?action=listar');
        const data = await response.json();
        
        if (data.sucesso) {
            AppState.servicos = data.servicos;
            renderizarServicos(data.servicos);
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
        mostrarErro('Erro ao carregar serviços');
    }
}

/**
 * Renderiza cards de serviços
 */
function renderizarServicos(servicos) {
    const container = document.getElementById('lista-servicos');
    
    if (servicos.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-3xl mb-2"></i>
                <p>Nenhum serviço cadastrado</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = servicos.map(servico => `
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="h-12 w-12 bg-gradient-to-r from-pink-500 to-purple-500 rounded-lg flex items-center justify-center text-white">
                        <i class="${servico.icone || 'fas fa-circle'}"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">${escapeHtml(servico.titulo)}</h3>
                        <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">${servico.categoria}</span>
                    </div>
                </div>
                <span class="${servico.ativo == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'} text-xs px-2 py-1 rounded-full">
                    ${servico.ativo == 1 ? 'Ativo' : 'Inativo'}
                </span>
            </div>
            
            <p class="text-sm text-gray-600 mb-4">${escapeHtml(servico.descricao)}</p>
            
            <div class="space-y-2 text-sm text-gray-500">
                ${servico.telefone ? `<p><i class="fas fa-phone w-4"></i> ${escapeHtml(servico.telefone)}</p>` : ''}
                ${servico.email ? `<p><i class="fas fa-envelope w-4"></i> ${escapeHtml(servico.email)}</p>` : ''}
                ${servico.horario ? `<p><i class="fas fa-clock w-4"></i> ${escapeHtml(servico.horario)}</p>` : ''}
            </div>
            
            <div class="flex space-x-2 mt-4 pt-4 border-t border-gray-200">
                <button onclick="editarServico(${servico.id})" class="flex-1 px-3 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors text-sm">
                    <i class="fas fa-edit mr-1"></i>
                    Editar
                </button>
                <button onclick="deletarServico(${servico.id})" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors text-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Abre modal para novo serviço
 */
function abrirModalServico(servico = null) {
    const modal = document.getElementById('modal-servico');
    const titulo = document.getElementById('modal-servico-titulo');
    const form = document.getElementById('form-servico');
    
    // Limpa o formulário
    form.reset();
    
    if (servico) {
        // Modo edição
        titulo.textContent = 'Editar Serviço';
        document.getElementById('servico-id').value = servico.id;
        document.getElementById('servico-titulo').value = servico.titulo;
        document.getElementById('servico-descricao').value = servico.descricao;
        document.getElementById('servico-icone').value = servico.icone || '';
        document.getElementById('servico-categoria').value = servico.categoria;
        document.getElementById('servico-telefone').value = servico.telefone || '';
        document.getElementById('servico-email').value = servico.email || '';
        document.getElementById('servico-horario').value = servico.horario || '';
        document.getElementById('servico-endereco').value = servico.endereco || '';
        document.getElementById('servico-link').value = servico.link || '';
        document.getElementById('servico-ativo').checked = servico.ativo == 1;
    } else {
        // Modo criação
        titulo.textContent = 'Novo Serviço';
        document.getElementById('servico-id').value = '';
        document.getElementById('servico-ativo').checked = true;
    }
    
    modal.classList.remove('hidden');
}

/**
 * Edita um serviço existente
 */
async function editarServico(id) {
    try {
        const response = await fetch(API_BASE + `servicos_api.php?action=buscar&id=${id}`);
        const data = await response.json();
        
        if (data.sucesso) {
            abrirModalServico(data.servico);
        }
    } catch (error) {
        console.error('Erro ao buscar serviço:', error);
    }
}

/**
 * Salva um serviço (criar ou atualizar)
 */
async function salvarServico(e) {
    e.preventDefault();
    
    const id = document.getElementById('servico-id').value;
    const dados = {
        titulo: document.getElementById('servico-titulo').value,
        descricao: document.getElementById('servico-descricao').value,
        icone: document.getElementById('servico-icone').value,
        categoria: document.getElementById('servico-categoria').value,
        telefone: document.getElementById('servico-telefone').value,
        email: document.getElementById('servico-email').value,
        horario: document.getElementById('servico-horario').value,
        endereco: document.getElementById('servico-endereco').value,
        link: document.getElementById('servico-link').value,
        ativo: document.getElementById('servico-ativo').checked ? 1 : 0
    };
    
    const action = id ? 'atualizar' : 'criar';
    if (id) dados.id = parseInt(id);
    
    try {
        const response = await fetch(API_BASE + `servicos_api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso(id ? 'Serviço atualizado!' : 'Serviço criado!');
            carregarServicos();
            fecharModal('modal-servico');
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao salvar serviço:', error);
        mostrarErro('Erro ao salvar serviço');
    }
}

/**
 * Deleta um serviço
 */
async function deletarServico(id) {
    if (!confirm('Tem certeza que deseja deletar este serviço?')) {
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'servicos_api.php?action=deletar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Serviço deletado!');
            carregarServicos();
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao deletar serviço:', error);
        mostrarErro('Erro ao deletar serviço');
    }
}

// ============================================
// GERENCIAMENTO DE BANNERS E IMAGENS
// ============================================

/**
 * Faz upload de um banner
 */
async function fazerUploadBanner(numero, arquivo) {
    if (!arquivo) return;
    
    const formData = new FormData();
    formData.append('imagem', arquivo);
    formData.append('tipo', 'banner');
    formData.append('numero', numero);
    
    try {
        mostrarSucesso('Fazendo upload...');
        
        const response = await fetch(API_BASE + 'upload_imagem_api.php?action=upload', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Banner atualizado com sucesso!');
            
            // Atualiza preview
            const preview = document.getElementById(`preview-banner${numero}`);
            preview.src = data.arquivo.url + '?t=' + new Date().getTime();
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao fazer upload:', error);
        mostrarErro('Erro ao fazer upload do banner');
    }
}

/**
 * Carrega galeria de imagens
 */
async function carregarImagens() {
    try {
        const container = document.getElementById('galeria-imagens');
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Carregando imagens...</p>
            </div>
        `;
        
        const response = await fetch(API_BASE + 'upload_imagem_api.php?action=listar&tipo=todos');
        const data = await response.json();
        
        if (data.sucesso) {
            AppState.imagens = data.imagens;
            renderizarGaleriaImagens(data.imagens);
        }
    } catch (error) {
        console.error('Erro ao carregar imagens:', error);
        mostrarErro('Erro ao carregar imagens');
    }
}

/**
 * Renderiza galeria de imagens
 */
function renderizarGaleriaImagens(imagens) {
    const container = document.getElementById('galeria-imagens');
    
    if (imagens.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-gray-500">
                <i class="fas fa-image text-3xl mb-2"></i>
                <p>Nenhuma imagem encontrada</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = imagens.map(imagem => `
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <div class="aspect-w-1 aspect-h-1 bg-gray-100">
                <img src="${imagem.url}" alt="${escapeHtml(imagem.nome)}" class="w-full h-48 object-cover">
            </div>
            <div class="p-3">
                <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(imagem.nome)}</p>
                <p class="text-xs text-gray-500">${imagem.tamanho_formatado}</p>
                <button onclick="deletarImagem(${imagem.id})" class="mt-2 w-full px-3 py-1 bg-red-100 text-red-600 rounded text-xs hover:bg-red-200 transition-colors">
                    <i class="fas fa-trash mr-1"></i>
                    Deletar
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Faz upload de imagem geral
 */
async function fazerUploadImagem(arquivo) {
    if (!arquivo) return;
    
    const formData = new FormData();
    formData.append('imagem', arquivo);
    formData.append('tipo', 'geral');
    
    try {
        mostrarSucesso('Fazendo upload...');
        
        const response = await fetch(API_BASE + 'upload_imagem_api.php?action=upload', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Imagem enviada com sucesso!');
            carregarImagens();
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao fazer upload:', error);
        mostrarErro('Erro ao fazer upload da imagem');
    }
}

/**
 * Deleta uma imagem
 */
async function deletarImagem(id) {
    if (!confirm('Tem certeza que deseja deletar esta imagem?')) {
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'upload_imagem_api.php?action=deletar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            mostrarSucesso('Imagem deletada!');
            carregarImagens();
        } else {
            mostrarErro(data.mensagem);
        }
    } catch (error) {
        console.error('Erro ao deletar imagem:', error);
        mostrarErro('Erro ao deletar imagem');
    }
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Fecha um modal
 */
function fecharModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

/**
 * Mostra mensagem de sucesso
 */
function mostrarSucesso(mensagem) {
    // Implementação simples com alert (pode ser melhorada com toast/notification)
    alert('✓ ' + mensagem);
}

/**
 * Mostra mensagem de erro
 */
function mostrarErro(mensagem) {
    alert('✗ ' + mensagem);
}

/**
 * Escapa HTML para prevenir XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Traduz status para português
 */
function traduzirStatus(status) {
    const traducoes = {
        'pendente': 'Pendente',
        'em_andamento': 'Em Andamento',
        'resolvido': 'Resolvido',
        'cancelado': 'Cancelado'
    };
    return traducoes[status] || status;
}

/**
 * Formata data para exibição
 */
function formatarData(dataStr) {
    const data = new Date(dataStr);
    return data.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Exporta funções globais necessárias
window.visualizarDemanda = visualizarDemanda;
window.atualizarStatusDemanda = atualizarStatusDemanda;
window.adicionarNota = adicionarNota;
window.deletarDemanda = deletarDemanda;
window.editarServico = editarServico;
window.deletarServico = deletarServico;
window.deletarImagem = deletarImagem;
window.fecharModal = fecharModal;
