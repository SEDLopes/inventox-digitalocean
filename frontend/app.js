/**
 * InventoX - Frontend Application
 * Gestão de Inventário com Scanner de Código de Barras
 * 
 * Versão: 1.1.0
 * Última atualização: 2024-11-08
 */

// Base da API: usar o mesmo domínio/host da app (funciona em dev e produção)
const API_BASE = `${location.origin.replace(/\/$/, '')}/api`;

// Usar logger condicional se disponível
const log = window.logger || console;
const logError = window.logger?.error || console.error;
const logWarn = window.logger?.warn || console.warn;
const logDebug = window.logger?.debug || (() => {});
let currentSessionId = null;
let currentItemId = null;
let currentBarcode = null;
let currentItemsPage = 1;
let currentItemsSearch = '';
let currentUsersPage = 1;
let currentUsersSearch = '';
let currentHistoryPage = 1;
let currentCategoriesSearch = '';
let scanner = null;
let codeReader = null;
let isMobileDevice = false;
let cameraPermissionGranted = false;
let availableCameras = [];
let currentCameraId = null;
let currentFacingMode = 'environment';
const isIOSDevice = (() => {
    const ua = navigator.userAgent || '';
    const platform = navigator.platform || '';
    return (/iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1));
})();

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    detectMobileDevice();
    checkAuth();
    initEventListeners();
    
    // Aguardar ZXing carregar (pode levar alguns milissegundos)
    setTimeout(() => {
        initZXing();
        if (isMobileDevice) {
            requestCameraPermissionOnMobile();
        }
    }, 100);
});

// Verificar autenticação
async function checkAuth() {
    // Verificar se existe sessão no sessionStorage
    const username = sessionStorage.getItem('username');
    if (username) {
        // Verificar se a sessão ainda é válida no servidor
        try {
            const response = await fetch(`${API_BASE}/stats.php`, {
                method: 'GET',
                credentials: 'include'
            });
            
            if (response.ok) {
                // Sessão válida, mostrar dashboard
                showDashboard(username);
                return;
            } else {
                // Sessão inválida, limpar dados locais
                log.debug('Sessão expirada, limpando dados locais');
                sessionStorage.clear();
            }
        } catch (error) {
            // Erro na verificação, limpar dados locais
            log.debug('Erro ao verificar sessão, limpando dados locais');
            sessionStorage.clear();
        }
    }
    
    // Mostrar login se não há sessão válida
    showLogin();
}

// Mostrar login
function showLogin() {
    const loginSection = document.getElementById('loginSection');
    const dashboardSection = document.getElementById('dashboardSection');
    if (loginSection) loginSection.classList.remove('hidden');
    if (dashboardSection) dashboardSection.classList.add('hidden');
}

// Mostrar dashboard
function showDashboard(username) {
    const loginSection = document.getElementById('loginSection');
    const dashboardSection = document.getElementById('dashboardSection');
    const userInfo = document.getElementById('userInfo');
    const logoutBtn = document.getElementById('logoutBtn');
    
    if (loginSection) loginSection.classList.add('hidden');
    if (dashboardSection) dashboardSection.classList.remove('hidden');
    if (userInfo) userInfo.textContent = `Olá, ${username}`;
    if (logoutBtn) logoutBtn.classList.remove('hidden');
    
    // Verificar role do utilizador e mostrar tabs apropriadas
    const userRole = sessionStorage.getItem('userRole');
    const usersTabBtn = document.getElementById('usersTabBtn');
    const historyTabBtn = document.getElementById('historyTabBtn');
    if (userRole === 'admin') {
        if (usersTabBtn) usersTabBtn.classList.remove('hidden');
        if (historyTabBtn) historyTabBtn.classList.remove('hidden');
    } else {
        if (usersTabBtn) usersTabBtn.classList.add('hidden');
        if (historyTabBtn) historyTabBtn.classList.add('hidden');
    }
    
    // Carregar dados do dashboard
    loadDashboard();
    loadSessions();
}

// Event Listeners
function initEventListeners() {
    // Login
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    
    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const tabName = e.target.dataset.tab;
            switchTab(tabName);
            
            // Carregar dados quando mudar de tab
            if (tabName === 'dashboard') {
                loadDashboard();
            } else if (tabName === 'items') {
                loadItems();
            } else if (tabName === 'categories') {
                loadCategories();
            } else if (tabName === 'history') {
                loadStockHistory();
            } else if (tabName === 'users') {
                loadUsers();
            } else if (tabName === 'companies') {
                loadCompanies();
            } else if (tabName === 'warehouses') {
                loadWarehouses();
            }
        });
    });
    
    // Scanner
    document.getElementById('startScannerBtn').addEventListener('click', showCountSetupModal);
    document.getElementById('stopScannerBtn').addEventListener('click', stopScanner);
    const switchBtn = document.getElementById('switchCameraBtn');
    if (switchBtn) {
        switchBtn.addEventListener('click', async () => {
                logDebug('Switch camera clicked - current facing mode:', currentFacingMode);
                // Em mobile, sempre alternar por facingMode (mais confiável)
                if (isMobileDevice) {
                    const newFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                    logDebug('Switching to facing mode:', newFacingMode);
                await switchCamera(null, newFacingMode);
            } else if (availableCameras && availableCameras.length > 1) {
                const idx = availableCameras.findIndex(d => d.deviceId === currentCameraId);
                const nextIdx = (idx >= 0 ? idx + 1 : 0) % availableCameras.length;
                await switchCamera(availableCameras[nextIdx].deviceId);
            } else {
                showToast('Nenhuma câmara alternativa disponível', 'warning');
            }
        });
    }
    const cameraSelect = document.getElementById('cameraSelect');
    if (cameraSelect) {
        cameraSelect.addEventListener('change', async (e) => {
            const deviceId = e.target.value;
            if (deviceId && deviceId !== currentCameraId) {
                await switchCamera(deviceId);
            }
        });
    }
    document.getElementById('manualBarcode').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            handleBarcode(e.target.value);
        }
    });
    
    // Botão de processar código manual
    const processManualBtn = document.getElementById('processManualBtn');
    if (processManualBtn) {
        processManualBtn.addEventListener('click', () => {
            const manualBarcodeField = document.getElementById('manualBarcode');
            if (manualBarcodeField && manualBarcodeField.value.trim()) {
                handleBarcode(manualBarcodeField.value.trim());
            } else {
                showToast('Digite um código de barras primeiro', 'warning');
            }
        });
    }
    
    // Session
    const createSessionBtn = document.getElementById('createSessionBtn');
    if (createSessionBtn) {
        createSessionBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const modal = document.getElementById('createSessionModal');
            if (modal) {
                // Limpar formulário antes de abrir
                const form = document.getElementById('createSessionForm');
                if (form) {
                    form.reset();
                }
                // Carregar empresas e resetar armazém
                loadCompaniesForSession();
                const warehouseSelect = document.getElementById('newSessionWarehouse');
                if (warehouseSelect) {
                    warehouseSelect.innerHTML = '<option value="">Primeiro selecione uma empresa</option>';
                    warehouseSelect.disabled = true;
                    warehouseSelect.classList.add('bg-gray-100');
                }
                modal.classList.remove('hidden');
            }
        });
    }
    
    const createSessionForm = document.getElementById('createSessionForm');
    if (createSessionForm) {
        createSessionForm.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Verificar se o formulário foi realmente submetido pelo utilizador
            // (não por validação automática do navegador)
            const submitButton = e.submitter;
            if (submitButton && submitButton.type === 'submit') {
                createSession();
            }
        });
    }
    
    const closeCreateSessionBtn = document.getElementById('closeCreateSession');
    const cancelCreateSessionBtn = document.getElementById('cancelCreateSession');
    if (closeCreateSessionBtn) {
        closeCreateSessionBtn.addEventListener('click', () => {
            const modal = document.getElementById('createSessionModal');
            if (modal) modal.classList.add('hidden');
        });
    }
    if (cancelCreateSessionBtn) {
        cancelCreateSessionBtn.addEventListener('click', () => {
            const modal = document.getElementById('createSessionModal');
            if (modal) modal.classList.add('hidden');
        });
    }
    
    // Event listener para mudança de empresa no modal de criação de sessão
    const newSessionCompany = document.getElementById('newSessionCompany');
    if (newSessionCompany) {
        newSessionCompany.addEventListener('change', handleNewSessionCompanyChange);
    }
    
    const sessionSelect = document.getElementById('sessionSelect');
    if (sessionSelect) {
        sessionSelect.addEventListener('change', (e) => {
            currentSessionId = e.target.value;
            if (currentSessionId) {
                loadSessionInfo(currentSessionId, true); // Mostrar detalhes quando selecionado manualmente
            }
        });
    }
    
    // Event listener para fechar o card de detalhes da sessão
    const closeSessionDetailsBtn = document.getElementById('closeSessionDetails');
    if (closeSessionDetailsBtn) {
        closeSessionDetailsBtn.addEventListener('click', closeSessionDetails);
    }
    
    // Item count
    const saveCountBtn = document.getElementById('saveCountBtn');
    if (saveCountBtn) {
        saveCountBtn.addEventListener('click', saveCount);
    }
    
    // Import
    const importFile = document.getElementById('importFile');
    const uploadBtn = document.getElementById('uploadBtn');
    const importForm = document.getElementById('importForm');
    if (importFile && uploadBtn) {
        uploadBtn.disabled = !importFile.files.length;
        importFile.addEventListener('change', (e) => {
            uploadBtn.disabled = !e.target.files.length;
        });
    }
    if (importForm) {
        importForm.addEventListener('submit', (e) => {
            e.preventDefault();
            uploadFile();
        });
    }
    
    // Refresh sessions
    const refreshSessionsBtn = document.getElementById('refreshSessionsBtn');
    if (refreshSessionsBtn) {
        refreshSessionsBtn.addEventListener('click', loadSessions);
    }

    // Items - filtro de stock baixo
    const lowStockCheckbox = document.getElementById('itemsLowStockOnly');
    if (lowStockCheckbox) {
        lowStockCheckbox.addEventListener('change', () => loadItems(1));
    }
    
    // Items management (elementos podem não existir em todas as vistas)
    const addItemBtn = document.getElementById('addItemBtn');
    if (addItemBtn) addItemBtn.addEventListener('click', () => openItemModal());
    const itemForm = document.getElementById('itemForm');
    if (itemForm) itemForm.addEventListener('submit', saveItem);
    const searchItemsBtn = document.getElementById('searchItemsBtn');
    if (searchItemsBtn) searchItemsBtn.addEventListener('click', () => loadItems(1));
    const itemsSearch = document.getElementById('itemsSearch');
    if (itemsSearch) {
        itemsSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                loadItems(1);
            }
        });
    }
    
    // Categories management
    const createCategoryBtn = document.getElementById('createCategoryBtn');
    if (createCategoryBtn) {
        createCategoryBtn.addEventListener('click', () => openCategoryModal());
    }
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', saveCategory);
    }
    const categorySearch = document.getElementById('categorySearch');
    if (categorySearch) {
        categorySearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                loadCategories();
            }
        });
        categorySearch.addEventListener('input', (e) => {
            // Debounce: carregar após 500ms sem digitar
            clearTimeout(categorySearch.searchTimeout);
            categorySearch.searchTimeout = setTimeout(() => {
                loadCategories();
            }, 500);
        });
    }
    
    // Companies management
    const createCompanyBtn = document.getElementById('createCompanyBtn');
    if (createCompanyBtn) {
        createCompanyBtn.addEventListener('click', () => openCompanyModal());
    }
    
    // Warehouses management
    const createWarehouseBtn = document.getElementById('createWarehouseBtn');
    if (createWarehouseBtn) {
        createWarehouseBtn.addEventListener('click', () => openWarehouseModal());
    }
    
    // History filters
    const historyType = document.getElementById('historyType');
    const historyDateFrom = document.getElementById('historyDateFrom');
    const historyDateTo = document.getElementById('historyDateTo');
    if (historyType) {
        historyType.addEventListener('change', () => loadStockHistory(1));
    }
    if (historyDateFrom) {
        historyDateFrom.addEventListener('change', () => loadStockHistory(1));
    }
    if (historyDateTo) {
        historyDateTo.addEventListener('change', () => loadStockHistory(1));
    }
    
    // Users management (admin only)
    const createUserBtn = document.getElementById('createUserBtn');
    const userForm = document.getElementById('userForm');
    const searchUsersBtn = document.getElementById('searchUsersBtn');
    const usersSearch = document.getElementById('usersSearch');
    
    if (createUserBtn) {
        createUserBtn.addEventListener('click', () => openUserModal());
    }
    if (userForm) {
        userForm.addEventListener('submit', saveUser);
    }
    if (searchUsersBtn) {
        searchUsersBtn.addEventListener('click', () => loadUsers(1));
    }
    if (usersSearch) {
        usersSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                loadUsers(1);
            }
        });
    }
    
    // Sessions - refreshSessionsBtn já foi declarado acima
}

// Inicializar ZXing
function initZXing() {
    try {
        logDebug('Tentando inicializar ZXing...');
        logDebug('ZXing disponível:', typeof ZXing !== 'undefined');
        
        if (typeof ZXing === 'undefined') {
            logWarn('ZXing library não foi carregada - objeto ZXing não encontrado');
            // Tentar novamente após 1 segundo
            setTimeout(initZXing, 1000);
            return;
        }
        
        if (!ZXing.BrowserMultiFormatReader) {
            logWarn('ZXing.BrowserMultiFormatReader não disponível');
            setTimeout(initZXing, 1000);
            return;
        }
        
        codeReader = new ZXing.BrowserMultiFormatReader();
        logDebug('ZXing inicializado com sucesso - codeReader criado');
        
        // Testar se conseguimos obter câmaras
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices()
                .then(devices => {
                    const videoDevices = devices.filter(device => device.kind === 'videoinput');
                    logDebug('Câmaras disponíveis:', videoDevices.length);
                    availableCameras = videoDevices;
                })
                .catch(err => logWarn('Erro ao enumerar câmaras:', err));
        }
        
    } catch (e) {
        logError('Erro ao inicializar ZXing:', e);
        // Tentar novamente após 2 segundos
        setTimeout(initZXing, 2000);
    }
}

// Detectar dispositivo móvel
function detectMobileDevice() {
    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    
    // Detectar iOS
    if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
        isMobileDevice = true;
        document.body.classList.add('ios-device');
        return;
    }
    
    // Detectar Android
    if (/android/i.test(userAgent)) {
        isMobileDevice = true;
        document.body.classList.add('android-device');
        return;
    }
    
    // Detectar outros dispositivos móveis
    if (/Mobile|mini|Fennec|Android|iP(ad|od|hone)/.test(userAgent)) {
        isMobileDevice = true;
        document.body.classList.add('mobile-device');
        return;
    }
    
    // Detectar por tamanho de ecrã (fallback)
    if (window.innerWidth <= 768) {
        isMobileDevice = true;
        document.body.classList.add('mobile-screen');
    }
    
    logDebug('Dispositivo móvel detectado:', isMobileDevice, 'iOS:', isIOSDevice);
}

// Solicitar permissão de câmara em dispositivos móveis
async function requestCameraPermissionOnMobile() {
    if (!isMobileDevice) return;
    
    try {
        logDebug('Solicitando permissão de câmara para dispositivo móvel...');
        
        // Forçar câmara traseira em mobile
        const constraints = {
            video: {
                facingMode: { exact: 'environment' }, // Forçar câmara traseira
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };
        
        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (exactError) {
            logWarn('Exact environment failed, trying ideal:', exactError);
            // Fallback para ideal se exact falhar
            constraints.video.facingMode = { ideal: 'environment' };
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        }
        
        // Permissão concedida - parar o stream por agora
        cameraPermissionGranted = true;
        stream.getTracks().forEach(track => track.stop());
        
        logDebug('Permissão de câmara concedida');
        
        // Mostrar feedback visual
        showToast('Câmara pronta para digitalização', 'success');
        
        // Atualizar interface para mostrar que a câmara está disponível
        updateCameraUI();
        
    } catch (error) {
        logError('Erro ao solicitar permissão de câmara:', error);
        cameraPermissionGranted = false;
        
        let message = 'Permissão de câmara necessária para digitalização';
        
        if (error.name === 'NotAllowedError') {
            message = 'Permissão de câmara negada. Ative nas definições do navegador.';
        } else if (error.name === 'NotFoundError') {
            message = 'Nenhuma câmara encontrada neste dispositivo.';
        } else if (error.name === 'NotSupportedError') {
            message = 'Câmara não suportada neste navegador.';
        }
        
        showToast(message, 'error');
    }
}

// Atualizar interface da câmara
function updateCameraUI() {
    const startBtn = document.getElementById('startScannerBtn');
    const placeholder = document.getElementById('scannerPlaceholder');
    
    if (cameraPermissionGranted && isMobileDevice) {
        if (startBtn) {
            startBtn.innerHTML = `
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Digitalizar Código
            `;
            startBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            startBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        }
        
        if (placeholder) {
            placeholder.innerHTML = `
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <p class="text-green-600 font-medium">Câmara Pronta</p>
                    <p class="text-gray-500 text-sm">Clique para iniciar digitalização</p>
                </div>
            `;
        }
    }
}

// Login
async function handleLogin(e) {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    // Validação básica
    if (!username || !password) {
        const loginError = document.getElementById('loginError');
        if (loginError) {
            loginError.textContent = 'Username e password são obrigatórios';
            loginError.classList.remove('hidden');
        }
        return;
    }
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include', // IMPORTANTE: Enviar cookies de sessão
            body: JSON.stringify({ username, password })
        });
        
        // Verificar se a resposta é válida
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Obter texto da resposta antes de fazer parse
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Resposta recebida:', responseText.substring(0, 500));
            hideLoading();
            showToast('Erro ao processar resposta do servidor', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            // NOTA: Cookies HttpOnly não são acessíveis via document.cookie
            // Isso é normal e esperado - o cookie será enviado automaticamente nas requisições
            // Verificar se cookies foram recebidos (pode estar vazio se HttpOnly)
            const cookies = document.cookie;
            logDebug('Cookies após login (pode estar vazio se HttpOnly):', cookies);
            
            // Armazenar dados no sessionStorage para referência
            sessionStorage.setItem('username', data.user.username);
            sessionStorage.setItem('userId', data.user.id);
            sessionStorage.setItem('userRole', data.user.role);
            
            // Aguardar um momento para garantir que os cookies foram definidos
            setTimeout(() => {
                showDashboard(data.user.username);
            }, 200);
        } else {
            const loginError = document.getElementById('loginError');
            if (loginError) {
                loginError.textContent = data.message || 'Erro ao fazer login';
                loginError.classList.remove('hidden');
            }
        }
    } catch (error) {
        hideLoading();
        const errorMessage = error.message || 'Erro ao fazer login';
        showToast(errorMessage, 'error');
        logError('Erro no login:', error);
        const loginError = document.getElementById('loginError');
        if (loginError) {
            loginError.textContent = errorMessage;
            loginError.classList.remove('hidden');
        }
    }
}

// Logout
async function handleLogout() {
    try {
        // Chamar API de logout para destruir sessão no servidor
        await fetch(`${API_BASE}/logout.php`, {
            method: 'POST',
            credentials: 'include'
        }).catch(() => {
            // Ignorar erros, continuar com logout local
        });
    } catch (error) {
        // Ignorar erros
    } finally {
        sessionStorage.clear();
        stopScanner();
        showLogin();
    }
}

// Trocar Tab
function switchTab(tabName) {
    // Atualizar botões
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active', 'bg-blue-600', 'text-white');
            btn.classList.remove('bg-gray-200', 'text-gray-700');
        } else {
            btn.classList.remove('active', 'bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-200', 'text-gray-700');
        }
    });
    
    // Mostrar/ocultar conteúdo
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    document.getElementById(`${tabName}Tab`).classList.remove('hidden');
}

// Mostrar Modal de Configuração de Contagem
async function showCountSetupModal() {
    document.getElementById('countSetupModal').classList.remove('hidden');
    await loadCompaniesForSetup();
    
    // Event listeners para o modal
    document.getElementById('setupCompanySelect').addEventListener('change', handleCompanyChange);
    document.getElementById('setupWarehouseSelect').addEventListener('change', handleWarehouseChange);
    document.getElementById('setupCreateNewSessionBtn').addEventListener('click', toggleNewSessionForm);
}

// Fechar Modal de Configuração
function closeCountSetupModal() {
    const modal = document.getElementById('countSetupModal');
    if (modal) {
        modal.classList.add('hidden');
        // Limpar seleções
        const companySelect = document.getElementById('setupCompanySelect');
        const warehouseSelect = document.getElementById('setupWarehouseSelect');
        const sessionSelect = document.getElementById('setupSessionSelect');
        const newSessionForm = document.getElementById('setupNewSessionForm');
        const createBtn = document.getElementById('setupCreateNewSessionBtn');
        
        if (companySelect) companySelect.value = '';
        if (warehouseSelect) {
            warehouseSelect.value = '';
            warehouseSelect.disabled = true;
        }
        if (sessionSelect) {
            sessionSelect.value = '';
            sessionSelect.disabled = true;
        }
        if (newSessionForm) newSessionForm.classList.add('hidden');
        if (createBtn) createBtn.classList.add('hidden');
    }
}

// Tornar função global para onclick handlers
window.closeCountSetupModal = closeCountSetupModal;

// Carregar Empresas para o Modal
async function loadCompaniesForSetup() {
    try {
        const response = await fetch(`${API_BASE}/companies.php?active_only=true`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.companies) {
            const select = document.getElementById('setupCompanySelect');
            select.innerHTML = '<option value="">Selecione uma empresa...</option>';
            
            data.companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name + (company.code ? ` (${company.code})` : '');
                select.appendChild(option);
            });
        }
    } catch (error) {
        logError('Erro ao carregar empresas:', error);
        showToast('Erro ao carregar empresas', 'error');
    }
}

// Quando Empresa é Selecionada
async function handleCompanyChange() {
    const companyId = document.getElementById('setupCompanySelect').value;
    const warehouseSelect = document.getElementById('setupWarehouseSelect');
    const sessionSelect = document.getElementById('setupSessionSelect');
    
    if (!companyId) {
        warehouseSelect.innerHTML = '<option value="">Primeiro selecione uma empresa</option>';
        warehouseSelect.disabled = true;
        sessionSelect.innerHTML = '<option value="">Primeiro selecione empresa e armazém</option>';
        sessionSelect.disabled = true;
        return;
    }
    
    // Carregar armazéns da empresa
    try {
        const response = await fetch(`${API_BASE}/warehouses.php?company_id=${companyId}&active_only=true`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.warehouses) {
            warehouseSelect.innerHTML = '<option value="">Selecione um armazém...</option>';
            
            data.warehouses.forEach(warehouse => {
                const option = document.createElement('option');
                option.value = warehouse.id;
                option.textContent = warehouse.name + (warehouse.code ? ` (${warehouse.code})` : '');
                warehouseSelect.appendChild(option);
            });
            
            warehouseSelect.disabled = false;
            warehouseSelect.classList.remove('bg-gray-100');
        } else {
            warehouseSelect.innerHTML = '<option value="">Nenhum armazém encontrado</option>';
        }
        
        // Resetar sessão
        sessionSelect.innerHTML = '<option value="">Primeiro selecione empresa e armazém</option>';
        sessionSelect.disabled = true;
    } catch (error) {
        logError('Erro ao carregar armazéns:', error);
        showToast('Erro ao carregar armazéns', 'error');
    }
}

// Quando Armazém é Selecionado
async function handleWarehouseChange() {
    const companyId = document.getElementById('setupCompanySelect').value;
    const warehouseId = document.getElementById('setupWarehouseSelect').value;
    const sessionSelect = document.getElementById('setupSessionSelect');
    const createBtn = document.getElementById('setupCreateNewSessionBtn');
    
    if (!companyId || !warehouseId) {
        sessionSelect.innerHTML = '<option value="">Primeiro selecione empresa e armazém</option>';
        sessionSelect.disabled = true;
        createBtn.classList.add('hidden');
        return;
    }
    
    // Carregar sessões abertas para a empresa e armazém
    try {
        const response = await fetch(`${API_BASE}/session_count.php`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.sessions) {
            // Filtrar sessões abertas para esta empresa e armazém
            const filteredSessions = data.sessions.filter(s => 
                s.status === 'aberta' && 
                s.company_id == companyId && 
                s.warehouse_id == warehouseId
            );
            
            sessionSelect.innerHTML = '<option value="">Selecione uma sessão...</option>';
            
            filteredSessions.forEach(session => {
                const option = document.createElement('option');
                option.value = session.id;
                option.textContent = session.name;
                sessionSelect.appendChild(option);
            });
            
            sessionSelect.disabled = false;
            sessionSelect.classList.remove('bg-gray-100');
            createBtn.classList.remove('hidden');
        } else {
            sessionSelect.innerHTML = '<option value="">Nenhuma sessão encontrada</option>';
            createBtn.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erro ao carregar sessões:', error);
        showToast('Erro ao carregar sessões', 'error');
    }
}

// Toggle Form de Nova Sessão
function toggleNewSessionForm() {
    const form = document.getElementById('setupNewSessionForm');
    if (form) {
        form.classList.toggle('hidden');
    }
}

// Tornar função global para onclick handlers
window.toggleNewSessionForm = toggleNewSessionForm;

// Confirmar Configuração e Iniciar Scanner
async function confirmCountSetup() {
    const companyId = document.getElementById('setupCompanySelect').value;
    const warehouseId = document.getElementById('setupWarehouseSelect').value;
    const sessionId = document.getElementById('setupSessionSelect').value;
    const newSessionName = document.getElementById('setupSessionName').value;
    const newSessionDesc = document.getElementById('setupSessionDescription').value;
    const isCreatingNewSession = !document.getElementById('setupNewSessionForm').classList.contains('hidden');
    
    // Validações
    if (!companyId || !warehouseId) {
        showToast('Por favor, selecione empresa e armazém', 'error');
        return;
    }
    
    let finalSessionId = sessionId;
    
    // Se está criando nova sessão
    if (isCreatingNewSession && newSessionName) {
        if (!newSessionName.trim()) {
            showToast('Nome da sessão é obrigatório', 'error');
            return;
        }
        
        try {
            showLoading();
            const response = await fetch(`${API_BASE}/session_count.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: newSessionName,
                    description: newSessionDesc,
                    company_id: parseInt(companyId),
                    warehouse_id: parseInt(warehouseId)
                }),
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                finalSessionId = data.session_id;
                showToast('Sessão criada com sucesso', 'success');
            } else {
                showToast(data.message || 'Erro ao criar sessão', 'error');
                hideLoading();
                return;
            }
        } catch (error) {
            logError('Erro ao criar sessão:', error);
            showToast('Erro ao criar sessão', 'error');
            hideLoading();
            return;
        }
    } else if (!sessionId) {
        showToast('Por favor, selecione uma sessão ou crie uma nova', 'error');
        return;
    }
    
    // Definir sessão atual e iniciar scanner
    currentSessionId = finalSessionId;
    
    // Atualizar informação da sessão na UI (sem mostrar o card de detalhes)
    await loadSessionInfo(finalSessionId, false);
    
    hideLoading();
    closeCountSetupModal();
    
    // Iniciar scanner
    await startScanner();
}

// Iniciar Scanner (após configuração)
async function startScanner() {
    logDebug('startScanner called - isMobileDevice:', isMobileDevice, 'isIOSDevice:', isIOSDevice);
    logDebug('codeReader disponível:', !!codeReader);
    logDebug('ZXing disponível:', typeof ZXing !== 'undefined');
    
    if (!codeReader || typeof ZXing === 'undefined') {
        showToast('Scanner não disponível. Certifique-se que a biblioteca ZXing foi carregada.', 'error');
        logError('Scanner não pode ser iniciado - codeReader:', !!codeReader, 'ZXing:', typeof ZXing !== 'undefined');
        return;
    }
    
    try {
        const video = document.getElementById('scannerVideo');
        // iOS/Android: garantir inline e som desligado para autoplay pós-gesto
        if (video) {
            video.setAttribute('playsinline', 'true');
            video.setAttribute('muted', 'true');
            video.muted = true;
        }
        const canvas = document.getElementById('scannerCanvas');
        const placeholder = document.getElementById('scannerPlaceholder');
        
        // Configurações otimizadas para dispositivos móveis
        let constraints = { video: true };
        
        if (isMobileDevice) {
            constraints = {
                video: {
                    facingMode: { exact: 'environment' }, // Forçar câmara traseira
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    frameRate: { ideal: 30, max: 60 }
                }
            };
        }
        
        // Pedir permissão de câmara primeiro (para navegadores modernos)
        try {
            const testStream = await navigator.mediaDevices.getUserMedia(constraints);
            // Em alguns iOS, é necessário manter o stream aberto até iniciar a leitura
            setTimeout(() => {
                try { testStream.getTracks().forEach(track => track.stop()); } catch (_) {}
            }, 250);
        } catch (permError) {
            if (permError.name === 'NotAllowedError' || permError.name === 'PermissionDeniedError') {
                showToast('Permissão de câmara negada. Por favor, permita o acesso à câmara nas configurações do navegador.', 'error');
                return;
            } else if (permError.name === 'NotFoundError' || permError.name === 'DevicesNotFoundError') {
                showToast('Nenhuma câmara encontrada. Pode usar o campo de texto para introduzir o código manualmente.', 'warning');
                placeholder.innerHTML = '<p class="text-gray-500">Nenhuma câmara disponível. Use o campo abaixo para introduzir o código manualmente.</p>';
                return;
            }
        }
        
        // Obter lista de câmaras
        let videoInputDevices = [];
        try {
            videoInputDevices = await codeReader.listVideoInputDevices();
        } catch (listError) {
            console.error('Erro ao listar câmaras:', listError);
            // Tentar mesmo assim - algumas versões do ZXing não suportam listVideoInputDevices
            videoInputDevices = [];
        }
        
        // Guardar e preencher seletor
        availableCameras = videoInputDevices || [];
        populateCameraSelect(availableCameras);

        // Preferir traseira se possível
        let selectedDeviceId = null;
        if (availableCameras.length > 0) {
            const rearRegex = /(back|traseira|rear|environment)/i;
            const prefer = availableCameras.find(d => rearRegex.test(d.label || ''));
            selectedDeviceId = (prefer && prefer.deviceId) || availableCameras[0].deviceId;
        }

        if (!selectedDeviceId && videoInputDevices.length === 0) {
            // Tentar usar undefined/null para usar a câmara padrão
            console.warn('Nenhuma câmara listada, tentando usar câmara padrão...');
        }
        
        // Iniciar leitura (usar null/undefined se não houver deviceId para usar câmara padrão)
        currentCameraId = selectedDeviceId || null;

        // Fallback: em iOS alguns browsers não aceitam deviceId. Tentar via constraints diretamente
        const startDecode = async () => {
            if (codeReader.decodeFromConstraints) {
                let constraintsForDecode;
                if (currentCameraId) {
                    constraintsForDecode = { video: { deviceId: { exact: currentCameraId } } };
                } else if (isMobileDevice) {
                    constraintsForDecode = { video: { facingMode: { exact: currentFacingMode } } };
                } else {
                    constraintsForDecode = { video: { facingMode: { ideal: 'environment' } } };
                }
                
                try {
                    return await codeReader.decodeFromConstraints(constraintsForDecode, video, onDecodeCallback);
                } catch (constraintError) {
                    // Fallback se exact falhar - tentar com ideal
                    console.warn('Exact constraint failed, trying ideal:', constraintError);
                    if (isMobileDevice && constraintsForDecode.video.facingMode?.exact) {
                        constraintsForDecode.video.facingMode = { ideal: currentFacingMode };
                        return await codeReader.decodeFromConstraints(constraintsForDecode, video, onDecodeCallback);
                    }
                    throw constraintError;
                }
            }
            return codeReader.decodeFromVideoDevice(currentCameraId, video, onDecodeCallback);
        };

        const onDecodeCallback = (result, err) => {
            if (result) {
                let barcodeText = null;
                try {
                    logDebug('Scanner detectou resultado:', result);
                    
                    if (typeof result.getText === 'function') {
                        barcodeText = result.getText();
                        logDebug('Código extraído via getText():', barcodeText);
                    } else if (result.text) {
                        barcodeText = result.text;
                        logDebug('Código extraído via .text:', barcodeText);
                    } else if (typeof result === 'string') {
                        barcodeText = result;
                        logDebug('Código como string:', barcodeText);
                    }
                    
                    if (barcodeText && barcodeText.trim()) {
                        logDebug('Código de barras detectado:', barcodeText);
                        
                        // Atualizar status
                        const scannerStatusText = document.getElementById('scannerStatusText');
                        if (scannerStatusText) {
                            scannerStatusText.textContent = `Processando código: ${barcodeText.trim()}`;
                        }
                        
                        if (isMobileDevice && navigator.vibrate) navigator.vibrate(200);
                        handleBarcode(barcodeText.trim());
                        stopScanner();
                    } else {
                        logDebug('Resultado vazio ou inválido');
                    }
                } catch (e) {
                    logError('Erro ao processar resultado do scanner:', e);
                }
            }
            
            // Ignorar erros esperados do fluxo de leitura contínua
            if (err) {
                const errorName = err.name || '';
                const errorMessage = err.message || err.toString() || '';
                
                // Erros esperados quando não há código de barras visível
                const isExpectedError = 
                    /NotFound|Checksum|Format/.test(errorName) ||
                    /No MultiFormat Readers/.test(errorMessage) ||
                    /No MultiFormat Readers/.test(errorName);
                
                if (!isExpectedError) {
                    // Apenas logar erros inesperados
                    logDebug('Decode callback error:', err);
                }
            }
        };

        logDebug('Iniciando decodificação...');
        await startDecode();
        logDebug('Decodificação iniciada com sucesso');
        
        // Mostrar o container do scanner e o vídeo
        const scannerContainer = document.getElementById('scanner');
        if (scannerContainer) {
            scannerContainer.classList.remove('hidden');
            logDebug('Scanner container mostrado');
        }
        if (placeholder) {
            placeholder.classList.add('hidden');
            logDebug('Placeholder escondido');
        }
        if (video) {
            video.classList.remove('hidden');
            logDebug('Vídeo mostrado - dimensões:', video.videoWidth, 'x', video.videoHeight);
            
            // Verificar se o vídeo está realmente a reproduzir
            video.addEventListener('loadedmetadata', () => {
                logDebug('Vídeo metadata carregada - dimensões:', video.videoWidth, 'x', video.videoHeight);
            });
            
            video.addEventListener('playing', () => {
                logDebug('Vídeo está a reproduzir');
            });
        }
        
        document.getElementById('startScannerBtn').classList.add('hidden');
        document.getElementById('stopScannerBtn').classList.remove('hidden');
        
        // Mostrar indicador de status
        const scannerStatus = document.getElementById('scannerStatus');
        const scannerStatusText = document.getElementById('scannerStatusText');
        if (scannerStatus) {
            scannerStatus.classList.remove('hidden');
            if (scannerStatusText && currentSessionId) {
                scannerStatusText.textContent = `Sessão ativa - Pronto para escanear códigos`;
            }
        }
        
        logDebug('Scanner iniciado com sucesso');
        const camWrap = document.getElementById('cameraSelectWrapper');
        const camBtn = document.getElementById('switchCameraBtn');
        
        // Mostrar sempre o botão em dispositivos móveis
        if (isMobileDevice) {
            camBtn && camBtn.classList.remove('hidden');
            updateCameraButtonText();
            // Mostrar seletor apenas se houver múltiplas câmaras listadas
            if (availableCameras && availableCameras.length > 1) {
                camWrap && camWrap.classList.remove('hidden');
            } else {
                camWrap && camWrap.classList.add('hidden');
            }
        } else if (availableCameras && availableCameras.length > 1) {
            camWrap && camWrap.classList.remove('hidden');
            camBtn && camBtn.classList.remove('hidden');
            updateCameraButtonText();
        } else {
            camWrap && camWrap.classList.add('hidden');
            camBtn && camBtn.classList.add('hidden');
        }
        
        // Reinício automático se o stream terminar em mobile (capture failure)
        const restartOnEnd = () => {
            try {
                const stream = video.srcObject;
                if (!stream) return;
                stream.getTracks().forEach(track => {
                    track.addEventListener('ended', () => {
                        console.warn('MediaStreamTrack ended — a reiniciar câmara...');
                        stopScanner();
                        setTimeout(() => startScanner(), 200);
                    });
                });
            } catch (_) {}
        };
        restartOnEnd();

        showToast('Scanner iniciado. Aponte a câmara para o código de barras.', 'success');
        
    } catch (error) {
        console.error('Erro ao iniciar scanner:', error);
        
        // Mensagens de erro mais específicas
        let errorMessage = 'Erro ao iniciar scanner';
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Permissão de câmara negada. Por favor, permita o acesso à câmara.';
        } else if (error.name === 'NotFoundError') {
            errorMessage = 'Nenhuma câmara encontrada. Use o campo de texto para introduzir o código manualmente.';
        } else if (error.message) {
            errorMessage = `Erro: ${error.message}`;
        }
        
        showToast(errorMessage, 'error');
    }
}

// Parar Scanner
function stopScanner() {
    if (codeReader) {
        codeReader.reset();
    }
    
    const video = document.getElementById('scannerVideo');
    const placeholder = document.getElementById('scannerPlaceholder');
    const scannerContainer = document.getElementById('scanner');
    const startBtn = document.getElementById('startScannerBtn');
    const stopBtn = document.getElementById('stopScannerBtn');
    
    // Ocultar o container do scanner e o vídeo
    if (scannerContainer) scannerContainer.classList.add('hidden');
    if (video) video.classList.add('hidden');
    if (placeholder) placeholder.classList.remove('hidden');
    if (startBtn) startBtn.classList.remove('hidden');
    if (stopBtn) stopBtn.classList.add('hidden');
    
    const camWrap = document.getElementById('cameraSelectWrapper');
    const camBtn = document.getElementById('switchCameraBtn');
    const scannerStatus = document.getElementById('scannerStatus');
    
    if (camWrap) camWrap.classList.add('hidden');
    if (camBtn) camBtn.classList.add('hidden');
    if (scannerStatus) scannerStatus.classList.add('hidden');
}

// Preencher seletor de câmaras
function populateCameraSelect(devices) {
    const select = document.getElementById('cameraSelect');
    const wrapper = document.getElementById('cameraSelectWrapper');
    if (!select || !wrapper) return;
    select.innerHTML = '';
    if (!devices || devices.length === 0) {
        wrapper.classList.add('hidden');
        return;
    }
    devices.forEach((d, i) => {
        const opt = document.createElement('option');
        const label = d.label || `Câmara ${i + 1}`;
        opt.value = d.deviceId;
        opt.textContent = label;
        if (currentCameraId && d.deviceId === currentCameraId) opt.selected = true;
        select.appendChild(opt);
    });
}

// Alternar câmara em runtime
async function switchCamera(deviceId, facingModeOverride) {
    try {
        console.log('switchCamera called with deviceId:', deviceId, 'facingMode:', facingModeOverride);
        const video = document.getElementById('scannerVideo');
        if (!codeReader || !video) {
            console.error('CodeReader or video element not available');
            return;
        }
        
        // Parar scanner atual
        codeReader.reset();
        
        // Atualizar estado
        currentCameraId = deviceId || null;
        if (facingModeOverride) {
            currentFacingMode = facingModeOverride;
            console.log('Updated facing mode to:', currentFacingMode);
        }
        
        const callback = (result, err) => {
            if (result) {
                let barcodeText = null;
                try {
                    if (typeof result.getText === 'function') barcodeText = result.getText();
                    else if (result.text) barcodeText = result.text;
                    else if (typeof result === 'string') barcodeText = result;
                    if (barcodeText) {
                        if (isMobileDevice && navigator.vibrate) navigator.vibrate(200);
                        handleBarcode(barcodeText);
                        stopScanner();
                    }
                } catch (_) {}
            }
        };
        
        // Tentar diferentes métodos de inicialização
        let success = false;
        
        // Método 1: decodeFromConstraints (preferido para mobile)
        if (codeReader.decodeFromConstraints && !success) {
            try {
                let constraintsForDecode;
                if (currentCameraId) {
                    constraintsForDecode = { video: { deviceId: { exact: currentCameraId } } };
                } else {
                    constraintsForDecode = { 
                        video: { 
                            facingMode: { exact: currentFacingMode },
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        } 
                    };
                }
                
                console.log('Trying decodeFromConstraints with:', constraintsForDecode);
                await codeReader.decodeFromConstraints(constraintsForDecode, video, callback);
                success = true;
            } catch (constraintError) {
                console.warn('decodeFromConstraints failed:', constraintError);
                
                // Fallback: tentar com ideal em vez de exact
                if (!currentCameraId && currentFacingMode) {
                    try {
                        const fallbackConstraints = { 
                            video: { 
                                facingMode: { ideal: currentFacingMode },
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        };
                        console.log('Trying fallback constraints:', fallbackConstraints);
                        await codeReader.decodeFromConstraints(fallbackConstraints, video, callback);
                        success = true;
                    } catch (fallbackError) {
                        console.warn('Fallback constraints also failed:', fallbackError);
                    }
                }
            }
        }
        
        // Método 2: decodeFromVideoDevice (fallback)
        if (!success) {
            try {
                console.log('Trying decodeFromVideoDevice with deviceId:', currentCameraId);
                await codeReader.decodeFromVideoDevice(currentCameraId, video, callback);
                success = true;
            } catch (deviceError) {
                console.error('decodeFromVideoDevice failed:', deviceError);
            }
        }
        
        if (success) {
            populateCameraSelect(availableCameras);
            updateCameraButtonText();
            const cameraType = currentFacingMode === 'environment' ? 'traseira' : 'frontal';
            showToast(`Câmara ${cameraType} ativada.`, 'success');
        } else {
            throw new Error('Todos os métodos de inicialização falharam');
        }
        
    } catch (e) {
        console.error('Erro ao alternar câmara:', e);
        showToast('Não foi possível alternar a câmara. Tente reiniciar o scanner.', 'error');
    }
}

// Atualizar texto do botão de câmara
function updateCameraButtonText() {
    const btnText = document.getElementById('switchCameraBtnText');
    if (!btnText) return;
    
    if (isMobileDevice) {
        const currentType = currentFacingMode === 'environment' ? 'Traseira' : 'Frontal';
        const nextType = currentFacingMode === 'environment' ? 'Frontal' : 'Traseira';
        btnText.textContent = `${currentType} → ${nextType}`;
    } else {
        btnText.textContent = 'Trocar Câmara';
    }
}

// Processar Código de Barras
async function handleBarcode(barcode) {
    if (!barcode || !barcode.trim()) return;
    
    try {
        showLoading();
        
        // Validar barcode (não pode estar vazio ou ter caracteres inválidos)
        if (!barcode || typeof barcode !== 'string') {
            throw new Error('Código de barras inválido');
        }
        
        // Buscar item pelo barcode
        const response = await fetch(`${API_BASE}/get_item.php?barcode=${encodeURIComponent(barcode)}`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Resposta recebida:', responseText.substring(0, 500));
            hideLoading();
            showToast('Erro ao processar resposta do servidor', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success && data.item) {
            const item = data.item;
            
            // Mostrar informações do item
            document.getElementById('itemInfoCard').classList.remove('hidden');
            document.getElementById('scannerItemBarcode').textContent = item.barcode;
            document.getElementById('scannerItemName').textContent = item.name;
            document.getElementById('scannerItemQuantity').textContent = item.quantity || 0;
            
            // Preencher campo manual com o barcode real do item (não o código de referência digitado)
            document.getElementById('itemCardManualBarcode').value = item.barcode;
            
            // Valor padrão da quantidade contada = quantidade atual
            const currentQty = item.quantity || 0;
            document.getElementById('countedQuantity').value = currentQty;
            document.getElementById('countedQuantity').focus();
            document.getElementById('countedQuantity').select();
            
            // Guardar barcode atual para uso no saveCount (usar o barcode real do item)
            currentBarcode = item.barcode;
            currentItemId = item.id;
            
            // Adicionar botão para editar artigo
            const itemInfoCard = document.getElementById('itemInfoCard');
            const editBtn = itemInfoCard.querySelector('#editItemFromScanner');
            if (!editBtn) {
                const editButton = document.createElement('button');
                editButton.id = 'editItemFromScanner';
                editButton.className = 'mt-2 w-full bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg text-sm';
                editButton.textContent = 'Editar Artigo';
                editButton.onclick = () => {
                    openItemModal(item.id);
                    // Trocar para tab de artigos após abrir modal
                    switchTab('items');
                };
                
                // Encontrar o container dos botões e adicionar o botão de editar
                const saveCountBtn = itemInfoCard.querySelector('button[onclick="saveCount()"]');
                if (saveCountBtn && saveCountBtn.parentNode) {
                    saveCountBtn.parentNode.insertBefore(editButton, saveCountBtn);
                } else {
                    // Fallback: adicionar ao final do itemInfoContent
                    const itemInfoContent = itemInfoCard.querySelector('#itemInfoContent');
                    if (itemInfoContent) {
                        itemInfoContent.appendChild(editButton);
                    }
                }
            }
            
            // Mostrar feedback específico se foi encontrado por código de referência
            if (item.match_type === 'reference' && item.barcode !== barcode) {
                showToast(`Artigo encontrado por código de referência: ${item.name} (${item.barcode})`, 'info');
            } else {
                showToast(`Artigo encontrado: ${item.name}`, 'success');
            }
        } else {
            showToast(data.message || 'Artigo não encontrado', 'error');
            document.getElementById('itemInfoCard').classList.add('hidden');
        }
        
    } catch (error) {
        hideLoading();
        showToast('Erro ao processar código', 'error');
        console.error(error);
    }
}

// Carregar Empresas para o Modal de Criação de Sessão
async function loadCompaniesForSession() {
    const companySelect = document.getElementById('newSessionCompany');
    if (!companySelect) return;
    
    try {
        companySelect.innerHTML = '<option value="">Carregando empresas...</option>';
        const response = await fetch(`${API_BASE}/companies.php?active_only=true`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.companies) {
            companySelect.innerHTML = '<option value="">Selecione uma empresa...</option>';
            
            data.companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name + (company.code ? ` (${company.code})` : '');
                companySelect.appendChild(option);
            });
        } else {
            companySelect.innerHTML = '<option value="">Nenhuma empresa encontrada</option>';
        }
    } catch (error) {
        logError('Erro ao carregar empresas:', error);
        const companySelect = document.getElementById('newSessionCompany');
        if (companySelect) {
            companySelect.innerHTML = '<option value="">Erro ao carregar empresas</option>';
        }
    }
}

// Quando Empresa é Selecionada no Modal de Criação de Sessão
async function handleNewSessionCompanyChange() {
    const companyId = document.getElementById('newSessionCompany').value;
    const warehouseSelect = document.getElementById('newSessionWarehouse');
    
    if (!companyId) {
        if (warehouseSelect) {
            warehouseSelect.innerHTML = '<option value="">Primeiro selecione uma empresa</option>';
            warehouseSelect.disabled = true;
            warehouseSelect.classList.add('bg-gray-100');
        }
        return;
    }
    
    // Carregar armazéns da empresa
    try {
        if (warehouseSelect) {
            warehouseSelect.innerHTML = '<option value="">Carregando armazéns...</option>';
        }
        
        const response = await fetch(`${API_BASE}/warehouses.php?company_id=${companyId}&active_only=true`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.warehouses) {
            if (warehouseSelect) {
                warehouseSelect.innerHTML = '<option value="">Selecione um armazém...</option>';
                
                data.warehouses.forEach(warehouse => {
                    const option = document.createElement('option');
                    option.value = warehouse.id;
                    option.textContent = warehouse.name + (warehouse.code ? ` (${warehouse.code})` : '');
                    warehouseSelect.appendChild(option);
                });
                
                warehouseSelect.disabled = false;
                warehouseSelect.classList.remove('bg-gray-100');
            }
        } else {
            if (warehouseSelect) {
                warehouseSelect.innerHTML = '<option value="">Nenhum armazém encontrado</option>';
            }
        }
    } catch (error) {
        logError('Erro ao carregar armazéns:', error);
        if (warehouseSelect) {
            warehouseSelect.innerHTML = '<option value="">Erro ao carregar armazéns</option>';
        }
    }
}

// Criar Sessão
async function createSession() {
    const nameEl = document.getElementById('newSessionName');
    const descriptionEl = document.getElementById('newSessionDescription');
    const companyEl = document.getElementById('newSessionCompany');
    const warehouseEl = document.getElementById('newSessionWarehouse');
    
    if (!nameEl || !descriptionEl || !companyEl || !warehouseEl) {
        console.error('Elementos do formulário de sessão não encontrados');
        showToast('Erro: Formulário não encontrado', 'error');
        return;
    }
    
    const name = nameEl.value.trim();
    const description = descriptionEl.value.trim();
    const companyId = parseInt(companyEl.value);
    const warehouseId = parseInt(warehouseEl.value);
    
    // Validação básica - só validar se o formulário foi realmente submetido
    if (!companyId || !warehouseId) {
        showToast('Empresa e armazém são obrigatórios', 'error');
        return;
    }
    
    if (!name || name.length === 0) {
        // Focar no campo de nome para indicar o erro
        nameEl.focus();
        showToast('Nome da sessão é obrigatório', 'error');
        return;
    }
    
    if (name.length < 3) {
        showToast('Nome da sessão deve ter pelo menos 3 caracteres', 'error');
        return;
    }
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/session_count.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                name, 
                description,
                company_id: companyId,
                warehouse_id: warehouseId
            }),
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta do servidor', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            currentSessionId = data.session_id;
            loadSessions();
            const sessionSelect = document.getElementById('sessionSelect');
            const currentSessionInfo = document.getElementById('currentSessionInfo');
            const currentSessionName = document.getElementById('currentSessionName');
            
            if (sessionSelect) sessionSelect.classList.remove('hidden');
            if (currentSessionInfo) currentSessionInfo.classList.remove('hidden');
            if (currentSessionName) currentSessionName.textContent = name;
            
            // Limpar formulário
            if (nameEl) nameEl.value = '';
            if (descriptionEl) descriptionEl.value = '';
            if (companyEl) companyEl.value = '';
            if (warehouseEl) {
                warehouseEl.value = '';
                warehouseEl.disabled = true;
                warehouseEl.classList.add('bg-gray-100');
                warehouseEl.innerHTML = '<option value="">Primeiro selecione uma empresa</option>';
            }
            
            // Fechar modal
            const modal = document.getElementById('createSessionModal');
            if (modal) modal.classList.add('hidden');
            
            showToast('Sessão criada com sucesso');
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao criar sessão', 'error');
        console.error(error);
    }
}

// Guardar Contagem
async function saveCount() {
    // Se não houver sessão ativa, tentar encontrar uma sessão aberta ou criar uma automaticamente
    if (!currentSessionId) {
        // Tentar carregar sessões e encontrar uma aberta
        try {
            const sessionsResponse = await fetch(`${API_BASE}/session_count.php`, {
                method: 'GET',
                credentials: 'include'
            });
            
            if (sessionsResponse.ok) {
                const sessionsData = await sessionsResponse.json();
                
                if (sessionsData.success && sessionsData.sessions) {
                    // Procurar por uma sessão aberta
                    const openSession = sessionsData.sessions.find(s => s.status === 'aberta');
                    
                    if (openSession) {
                        // Usar a primeira sessão aberta encontrada
                        currentSessionId = openSession.id;
                        document.getElementById('sessionSelect').value = openSession.id;
                        loadSessionInfo(openSession.id, false); // Não mostrar detalhes automaticamente
                        showToast(`Usando sessão: ${openSession.name}`, 'info');
                    } else {
                        // Criar uma sessão automática se não houver nenhuma aberta
                        const autoSessionName = `Inventário ${new Date().toLocaleDateString('pt-PT')}`;
                        const createResponse = await fetch(`${API_BASE}/session_count.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                name: autoSessionName,
                                description: 'Sessão criada automaticamente'
                            }),
                            credentials: 'include'
                        });
                        
                        if (createResponse.ok) {
                            const createData = await createResponse.json();
                            if (createData.success) {
                                currentSessionId = createData.session_id;
                                loadSessions();
                                showToast('Sessão criada automaticamente', 'info');
                            } else {
                                showToast('Selecione ou crie uma sessão primeiro', 'error');
                                return;
                            }
                        } else {
                            showToast('Selecione ou crie uma sessão primeiro', 'error');
                            return;
                        }
                    }
                } else {
                    showToast('Selecione ou crie uma sessão primeiro', 'error');
                    return;
                }
            } else {
                showToast('Selecione ou crie uma sessão primeiro', 'error');
                return;
            }
        } catch (error) {
            console.error('Erro ao verificar sessões:', error);
            showToast('Selecione ou crie uma sessão primeiro', 'error');
            return;
        }
    }
    
    const barcode = currentBarcode || document.getElementById('itemCardManualBarcode').value;
    const countedQuantity = document.getElementById('countedQuantity').value;
    
    // Validações
    const barcodeValidation = validateBarcode(barcode);
    if (!barcodeValidation.valid) {
        showToast(barcodeValidation.message, 'error');
        return;
    }
    
    const quantityValidation = validateQuantity(countedQuantity);
    if (!quantityValidation.valid) {
        showToast(quantityValidation.message, 'error');
        return;
    }
    
    const qty = quantityValidation.value;
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/session_count.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: currentSessionId,
                barcode: barcode,
                counted_quantity: qty
            }),
            credentials: 'include' // Enviar cookies de sessão
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta do servidor', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast('Contagem guardada com sucesso', 'success');
            
            // Atualizar informações da sessão (sem mostrar detalhes)
            if (currentSessionId) {
                loadSessionInfo(currentSessionId, false);
            }
            
            // Fechar o card de informações do item após guardar
            document.getElementById('itemInfoCard').classList.add('hidden');
            
            // Limpar dados globais
            currentBarcode = null;
            currentItemId = null;
            
            // Limpar campos
            document.getElementById('countedQuantity').value = 0;
            document.getElementById('itemCardManualBarcode').value = '';
            
            // Reativar o scanner automaticamente para continuar escaneando
            setTimeout(async () => {
                try {
                    await startScanner();
                    showToast('Scanner reativado - pronto para o próximo código!', 'info');
                } catch (error) {
                    logError('Erro ao reativar scanner:', error);
                    showToast('Erro ao reativar scanner. Use entrada manual ou reinicie.', 'warning');
                }
            }, 500); // Pequeno delay para garantir que o card foi fechado
        } else {
            showToast(data.message || 'Erro ao guardar contagem', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao guardar contagem', 'error');
        console.error(error);
    }
}

// Carregar Sessões
async function loadSessions() {
    try {
        const response = await fetch(`${API_BASE}/session_count.php`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            showToast('Erro ao carregar sessões', 'error');
            return;
        }
        
        if (data.success) {
            const sessionsList = document.getElementById('sessionsList');
            const sessionSelect = document.getElementById('sessionSelect');
            
            sessionsList.innerHTML = '';
            sessionSelect.innerHTML = '<option value="">Selecione uma sessão...</option>';
            
            data.sessions.forEach(session => {
                // Lista de sessões
                const sessionCard = document.createElement('div');
                sessionCard.className = 'bg-gray-50 rounded-lg p-4 border border-gray-200';
                sessionCard.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-bold">${session.name}</h4>
                            <p class="text-sm text-gray-600">${session.description || ''}</p>
                            <p class="text-xs text-gray-500 mt-2">
                                Criado por: ${session.created_by} | 
                                Status: ${session.status} | 
                                Contagens: ${session.total_counts || 0}
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="loadSessionInfo(${session.id}, true)" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                Ver
                            </button>
                            <a href="${API_BASE}/export_session.php?id=${session.id}&format=csv" 
                               class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                Exportar
                            </a>
                        </div>
                    </div>
                `;
                sessionsList.appendChild(sessionCard);
                
                // Select de sessões
                const option = document.createElement('option');
                option.value = session.id;
                option.textContent = session.name;
                if (session.status === 'aberta') {
                    option.textContent += ' (Aberta)';
                }
                sessionSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar sessões:', error);
    }
}

// Carregar Info da Sessão
async function loadSessionInfo(sessionId, showDetails = false) {
    currentSessionId = sessionId;
    
    try {
        const response = await fetch(`${API_BASE}/session_count.php?id=${sessionId}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            showToast('Erro ao carregar sessão', 'error');
            return;
        }
        
        if (data.success) {
            const session = data.session;
            
            // Atualizar informações na área do scanner
            const currentSessionInfo = document.getElementById('currentSessionInfo');
            if (currentSessionInfo) {
                currentSessionInfo.classList.remove('hidden');
                document.getElementById('currentSessionName').textContent = session.name;
                document.getElementById('sessionCount').textContent = session.total_counts || 0;
            }
            
            const sessionSelect = document.getElementById('sessionSelect');
            if (sessionSelect) {
                sessionSelect.value = sessionId;
            }
            
            // Mostrar detalhes da sessão apenas se solicitado
            if (showDetails) {
                setTimeout(() => {
                    showSessionDetails(session, session.counts || []);
                }, 100);
            }
        }
    } catch (error) {
        console.error('Erro ao carregar sessão:', error);
        showToast('Erro ao carregar sessão', 'error');
    }
}

// Mostrar detalhes da sessão
function showSessionDetails(session, counts) {
    const detailsCard = document.getElementById('sessionDetailsCard');
    const detailsContent = document.getElementById('sessionDetailsContent');
    
    if (!detailsCard || !detailsContent) return;
    
    // Calcular estatísticas
    const totalCounts = counts.length;
    const discrepancies = counts.filter(count => count.difference !== 0).length;
    const totalItems = counts.reduce((sum, count) => sum + parseInt(count.counted_quantity || 0), 0);
    
    detailsContent.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Informações da Sessão -->
            <div>
                <h4 class="text-lg font-semibold mb-3">Informações Gerais</h4>
                <div class="space-y-2">
                    <p><strong>Nome:</strong> ${session.name}</p>
                    <p><strong>Descrição:</strong> ${session.description || 'N/A'}</p>
                    <p><strong>Empresa:</strong> ${session.company_name || 'N/A'} ${session.company_code ? `(${session.company_code})` : ''}</p>
                    <p><strong>Armazém:</strong> ${session.warehouse_name || 'N/A'} ${session.warehouse_code ? `(${session.warehouse_code})` : ''}</p>
                    <p><strong>Criado por:</strong> ${session.created_by}</p>
                    <p><strong>Status:</strong> <span class="px-2 py-1 rounded text-sm ${session.status === 'aberta' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">${session.status}</span></p>
                    <p><strong>Iniciado em:</strong> ${new Date(session.started_at).toLocaleString('pt-PT')}</p>
                    ${session.finished_at ? `<p><strong>Finalizado em:</strong> ${new Date(session.finished_at).toLocaleString('pt-PT')}</p>` : ''}
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div>
                <h4 class="text-lg font-semibold mb-3">Estatísticas</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600">${totalCounts}</p>
                        <p class="text-sm text-gray-600">Total de Contagens</p>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <p class="text-2xl font-bold text-yellow-600">${discrepancies}</p>
                        <p class="text-sm text-gray-600">Discrepâncias</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-2xl font-bold text-green-600">${totalItems}</p>
                        <p class="text-sm text-gray-600">Itens Contados</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <p class="text-2xl font-bold text-purple-600">${totalCounts > 0 ? Math.round((discrepancies / totalCounts) * 100) : 0}%</p>
                        <p class="text-sm text-gray-600">Taxa de Discrepância</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Contagens -->
        ${counts.length > 0 ? `
            <div class="mt-6">
                <h4 class="text-lg font-semibold mb-3">Contagens Realizadas</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Artigo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código de Barras</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qtd. Sistema</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qtd. Contada</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Diferença</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            ${counts.slice(0, 20).map(count => `
                                <tr class="${count.difference !== 0 ? 'bg-yellow-50' : ''}">
                                    <td class="px-4 py-2 text-sm">${count.item_name || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm font-mono">${count.barcode}</td>
                                    <td class="px-4 py-2 text-sm">${count.expected_quantity || count.system_quantity || 0}</td>
                                    <td class="px-4 py-2 text-sm">${count.counted_quantity}</td>
                                    <td class="px-4 py-2 text-sm ${count.difference > 0 ? 'text-green-600' : count.difference < 0 ? 'text-red-600' : ''}">${count.difference > 0 ? '+' : ''}${count.difference}</td>
                                    <td class="px-4 py-2 text-sm">${new Date(count.counted_at).toLocaleString('pt-PT')}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    ${counts.length > 20 ? `<p class="text-sm text-gray-500 mt-2">Mostrando 20 de ${counts.length} contagens. <a href="${API_BASE}/export_session.php?id=${session.id}&format=csv" class="text-blue-600 hover:underline">Exportar todas</a></p>` : ''}
                </div>
            </div>
        ` : '<div class="mt-6 text-center text-gray-500">Nenhuma contagem realizada ainda.</div>'}
        
        <!-- Ações -->
        <div class="mt-6 flex space-x-3">
            <a href="${API_BASE}/export_session.php?id=${session.id}&format=csv" 
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                Exportar CSV
            </a>
            <a href="${API_BASE}/export_session.php?id=${session.id}&format=json" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Exportar JSON
            </a>
        </div>
    `;
    
    detailsCard.classList.remove('hidden');
}

// Fechar detalhes da sessão
function closeSessionDetails() {
    const detailsCard = document.getElementById('sessionDetailsCard');
    if (detailsCard) {
        detailsCard.classList.add('hidden');
    }
}

// Fazer Upload de Ficheiro
async function uploadFile() {
    const fileInput = document.getElementById('importFile');
    const uploadBtn = document.getElementById('uploadBtn');
    const file = fileInput.files[0];
    
    // Validação
    const validation = validateImportFile(file);
    if (!validation.valid) {
        showToast(validation.message, 'error');
        return;
    }
    
    if (uploadBtn) uploadBtn.disabled = true;
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/items_import.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Resposta recebida:', responseText.substring(0, 500));
            hideLoading();
            showToast('Erro ao processar resposta do servidor', 'error');
            if (uploadBtn) uploadBtn.disabled = false;
            return;
        }
        
        if (!response.ok) {
            hideLoading();
            showToast(data.message || `Erro no upload (${response.status})`, 'error');
            if (uploadBtn) uploadBtn.disabled = false;
            return;
        }
        
        hideLoading();
        
        const resultDiv = document.getElementById('importResults');
        if (resultDiv) {
            resultDiv.classList.remove('hidden');
        }
        
        if (data.success) {
            if (resultDiv) {
                resultDiv.className = 'p-4 bg-green-50 rounded-lg text-green-800';
                const errorsHtml = (data.errors && data.errors.length)
                    ? `<div class="mt-2 text-red-700"><p class="font-semibold mb-1">Ocorreram alguns avisos/erros:</p><ul class="list-disc ml-5 text-sm">${data.errors.slice(0,10).map(e => `<li>${e}</li>`).join('')}</ul>${data.errors.length>10?`<p class=\"text-xs mt-1\">(+${data.errors.length-10} mais)</p>`:''}</div>`
                    : '';
                resultDiv.innerHTML = `
                    <p class="font-bold">Importação concluída com sucesso!</p>
                    <p class="text-sm mt-2">
                        Importados: ${data.imported || 0} | 
                        Atualizados: ${data.updated || 0}
                    </p>
                    ${errorsHtml}
                `;
            }
            fileInput.value = '';
            if (uploadBtn) uploadBtn.disabled = true;
        } else {
            if (resultDiv) {
                resultDiv.className = 'p-4 bg-red-50 rounded-lg text-red-800';
                resultDiv.innerHTML = `<p>${data.message}</p>`;
            }
            if (uploadBtn) uploadBtn.disabled = false;
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao fazer upload', 'error');
        console.error(error);
        if (uploadBtn) uploadBtn.disabled = false;
    }
}

// Utilitários
function showLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.remove('hidden');
}

function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.add('hidden');
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toast || !toastMessage) {
        console.log(`[${type.toUpperCase()}] ${message}`);
        return;
    }
    
    toastMessage.textContent = message;
    
    // Definir cor baseado no tipo
    let bgColor = 'bg-green-500'; // padrão: success
    if (type === 'error') {
        bgColor = 'bg-red-500';
    } else if (type === 'warning') {
        bgColor = 'bg-yellow-500';
    } else if (type === 'info') {
        bgColor = 'bg-blue-500';
    }
    
    toast.className = `fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 ${bgColor}`;
    toast.classList.remove('hidden');
    
    // Auto-hide após 3-5 segundos (mais tempo para erros)
    const duration = type === 'error' ? 5000 : 3000;
    setTimeout(() => {
        toast.classList.add('hidden');
    }, duration);
}

// ==========================================
// Dashboard Functions
// ==========================================

// Carregar Dashboard
async function loadDashboard() {
    try {
        const response = await fetch(`${API_BASE}/stats.php`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            return;
        }
        
        if (data.success && data.stats) {
            const stats = data.stats;
            
            // Atualizar cards
            document.getElementById('statTotalItems').textContent = stats.total_items || 0;
            document.getElementById('statLowStock').textContent = stats.low_stock_items || 0;
            document.getElementById('statOpenSessions').textContent = stats.open_sessions || 0;
            
            // Formatar valor do inventário em CVE (Escudos Cabo-verdianos)
            const value = stats.total_inventory_value || 0;
            document.getElementById('statInventoryValue').textContent = 
                new Intl.NumberFormat('pt-CV', { style: 'currency', currency: 'CVE' }).format(value);
            
            // Lista de stock baixo
            const lowStockList = document.getElementById('lowStockList');
            if (lowStockList) {
                if (stats.low_stock_list && stats.low_stock_list.length > 0) {
                    lowStockList.innerHTML = stats.low_stock_list.map(item => `
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg border border-red-200">
                            <div>
                                <p class="font-semibold">${item.name}</p>
                                <p class="text-sm text-gray-600">Código: ${item.barcode} | Stock: ${item.quantity}/${item.min_quantity}</p>
                            </div>
                            <span class="text-red-600 font-bold">Falta: ${item.shortage}</span>
                        </div>
                    `).join('');
                } else {
                    lowStockList.innerHTML = '<p class="text-gray-500 text-center py-4">Nenhum artigo com stock baixo</p>';
                }
            }
            
            // Top categorias
            const topCategoriesList = document.getElementById('topCategoriesList');
            if (topCategoriesList) {
                if (stats.top_categories && stats.top_categories.length > 0) {
                    topCategoriesList.innerHTML = stats.top_categories.map(cat => `
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-semibold">${cat.name}</p>
                            </div>
                            <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm font-semibold">
                                ${cat.items_count} artigos
                            </span>
                        </div>
                    `).join('');
                } else {
                    topCategoriesList.innerHTML = '<p class="text-gray-500 text-center py-4">Nenhuma categoria disponível</p>';
                }
            }
            
            // Banner de alerta de stock baixo
            const lowStockBanner = document.getElementById('lowStockBanner');
            if (lowStockBanner) {
                if ((stats.low_stock_items || 0) > 0) {
                    lowStockBanner.classList.remove('hidden');
                } else {
                    lowStockBanner.classList.add('hidden');
                }
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
    }
}

// ==========================================
// Items Management Functions
// ==========================================

// Variáveis de paginação e pesquisa (declaradas mais abaixo)

// Carregar Artigos
async function loadItems(page = 1, search = '') {
    try {
        showLoading();
        currentItemsPage = page;
        const itemsSearchEl = document.getElementById('itemsSearch');
        currentItemsSearch = search || (itemsSearchEl ? itemsSearchEl.value : '');
        
        const params = new URLSearchParams({
            page: page,
            limit: 20
        });
        
        if (currentItemsSearch) {
            params.append('search', currentItemsSearch);
        }
        const lowOnly = document.getElementById('itemsLowStockOnly');
        if (lowOnly && lowOnly.checked) {
            params.append('low_stock', 'true');
        }
        
        const response = await fetch(`${API_BASE}/items.php?${params.toString()}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao carregar artigos', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            const itemsList = document.getElementById('itemsList');
            itemsList.innerHTML = '';
            
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    const itemCard = document.createElement('div');
                    const isLow = (parseInt(item.quantity) || 0) <= (parseInt(item.min_quantity) || 0);
                    itemCard.className = `rounded-lg p-4 border shadow-sm ${isLow ? 'bg-red-50 border-red-300' : 'bg-gray-50 border-gray-200'}`;
                    itemCard.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <h4 class="font-bold text-lg ${isLow ? 'text-red-700' : ''}">${item.name}</h4>
                                    <span class="text-xs bg-gray-200 px-2 py-1 rounded">${item.barcode}</span>
                                    ${isLow ? '<span class="text-xs bg-red-500 text-white px-2 py-1 rounded">Stock Baixo</span>' : ''}
                                </div>
                                <p class="text-sm text-gray-600 mt-1">${item.description || 'Sem descrição'}</p>
                                <div class="mt-2 flex space-x-4 text-sm">
                                    <span><strong>Categoria:</strong> ${item.category_name || 'Sem categoria'}</span>
                                    <span><strong>Quantidade:</strong> ${item.quantity}</span>
                                    <span><strong>Preço:</strong> ${new Intl.NumberFormat('pt-CV', { style: 'currency', currency: 'CVE' }).format(item.unit_price || 0)}</span>
                                </div>
                                ${item.location ? `<p class="text-xs text-gray-500 mt-1"><strong>Local:</strong> ${item.location}</p>` : ''}
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <button onclick="editItem(${item.id})" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    Editar
                                </button>
                                <button onclick="deleteItem(${item.id})" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    `;
                    itemsList.appendChild(itemCard);
                });
                
                // Paginação
                if (data.pagination && data.pagination.pages > 1) {
                    const paginationDiv = document.getElementById('itemsPagination');
                    paginationDiv.innerHTML = '';
                    
                    for (let i = 1; i <= data.pagination.pages; i++) {
                        const btn = document.createElement('button');
                        btn.className = `px-4 py-2 rounded-lg ${i === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'}`;
                        btn.textContent = i;
                        btn.onclick = () => loadItems(i);
                        paginationDiv.appendChild(btn);
                    }
                } else {
                    document.getElementById('itemsPagination').innerHTML = '';
                }
            } else {
                itemsList.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhum artigo encontrado</p>';
            }
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao carregar artigos', 'error');
        console.error(error);
    }
}

// ==========================================
// Companies Management Functions
// ==========================================

async function loadCompanies() {
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/companies.php?active_only=false`, { credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();
        hideLoading();
        if (data.success) {
            const list = document.getElementById('companiesList');
            list.innerHTML = '';
            (data.companies || []).forEach(c => {
                const card = document.createElement('div');
                card.className = `rounded-2xl p-4 shadow-md border ${c.is_active ? 'bg-white border-gray-200' : 'bg-gray-100 border-gray-300 opacity-80'}`;
                card.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="text-lg font-bold">${c.name}</h4>
                            <p class="text-sm text-gray-600">${c.code || ''}</p>
                            ${c.address ? `<p class=\"text-xs text-gray-500 mt-1\">${c.address}</p>` : ''}
                        </div>
                        <div class="flex gap-2">
                            <button class="px-3 py-1 rounded bg-blue-600 text-white text-sm" onclick="openCompanyModal(${c.id})">Editar</button>
                            <button class="px-3 py-1 rounded bg-red-600 text-white text-sm" onclick="deleteCompany(${c.id})">Eliminar</button>
                        </div>
                    </div>`;
                list.appendChild(card);
            });
        }
    } catch (e) {
        hideLoading();
        console.error(e);
        showToast('Erro ao carregar empresas', 'error');
    }
}

function openCompanyModal(id = null) {
    document.getElementById('companyForm').reset();
    document.getElementById('companyId').value = '';
    document.getElementById('companyModalTitle').textContent = id ? 'Editar Empresa' : 'Nova Empresa';
    const modal = document.getElementById('companyModal');
    modal.classList.remove('hidden');
    if (id) {
        // carregar empresa
        fetch(`${API_BASE}/companies.php?id=${id}`, { credentials: 'include' })
            .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch { throw new Error('parse'); } })
            .then(data => {
                if (data.success && data.company) {
                    const c = data.company;
                    document.getElementById('companyId').value = c.id;
                    document.getElementById('companyName').value = c.name || '';
                    document.getElementById('companyCode').value = c.code || '';
                    document.getElementById('companyAddress').value = c.address || '';
                    document.getElementById('companyPhone').value = c.phone || '';
                    document.getElementById('companyEmail').value = c.email || '';
                    document.getElementById('companyTaxId').value = c.tax_id || '';
                    document.getElementById('companyActive').checked = !!c.is_active;
                }
            }).catch(() => showToast('Erro ao carregar empresa', 'error'));
    }
}

function closeCompanyModal() {
    document.getElementById('companyModal').classList.add('hidden');
}

document.addEventListener('submit', (e) => {
    if (e.target && e.target.id === 'companyForm') {
        e.preventDefault();
        saveCompany();
    }
});

async function saveCompany() {
    const id = document.getElementById('companyId').value;
    const payload = {
        name: document.getElementById('companyName').value,
        code: document.getElementById('companyCode').value,
        address: document.getElementById('companyAddress').value,
        phone: document.getElementById('companyPhone').value,
        email: document.getElementById('companyEmail').value,
        tax_id: document.getElementById('companyTaxId').value,
        is_active: document.getElementById('companyActive').checked
    };
    if (id) payload.id = parseInt(id);
    try {
        showLoading();
        const method = id ? 'PUT' : 'POST';
        const resp = await fetch(`${API_BASE}/companies.php`, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        hideLoading();
        if (data.success) {
            showToast('Empresa guardada com sucesso');
            closeCompanyModal();
            loadCompanies();
        } else {
            showToast(data.message || 'Erro ao guardar empresa', 'error');
        }
    } catch (e) {
        hideLoading();
        showToast('Erro ao guardar empresa', 'error');
    }
}

async function deleteCompany(id) {
    if (!confirm('Eliminar esta empresa?')) return;
    try {
        showLoading();
        const resp = await fetch(`${API_BASE}/companies.php?id=${id}`, { 
            method: 'DELETE',
            credentials: 'include'
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        hideLoading();
        if (data.success) { showToast('Empresa eliminada'); loadCompanies(); } else { showToast(data.message || 'Erro', 'error'); }
    } catch {
        hideLoading();
        showToast('Erro ao eliminar empresa', 'error');
    }
}

// ==========================================
// Warehouses Management Functions
// ==========================================

async function loadWarehouses() {
    try {
        showLoading();
        const resp = await fetch(`${API_BASE}/warehouses.php`, { credentials: 'include' });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        hideLoading();
        if (data.success) {
            const list = document.getElementById('warehousesList');
            list.innerHTML = '';
            (data.warehouses || []).forEach(w => {
                const card = document.createElement('div');
                card.className = `rounded-2xl p-4 shadow-md border ${w.is_active ? 'bg-white border-gray-200' : 'bg-gray-100 border-gray-300 opacity-80'}`;
                card.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="text-lg font-bold">${w.name}</h4>
                            <p class="text-sm text-gray-600">${w.company_name || ''} ${w.code ? '• ' + w.code : ''}</p>
                            ${w.location ? `<p class=\"text-xs text-gray-500 mt-1\">${w.location}</p>` : ''}
                        </div>
                        <div class="flex gap-2">
                            <button class="px-3 py-1 rounded bg-blue-600 text-white text-sm" onclick="openWarehouseModal(${w.id})">Editar</button>
                            <button class="px-3 py-1 rounded bg-red-600 text-white text-sm" onclick="deleteWarehouse(${w.id})">Eliminar</button>
                        </div>
                    </div>`;
                list.appendChild(card);
            });
        }
    } catch (e) {
        hideLoading();
        showToast('Erro ao carregar armazéns', 'error');
    }
}

async function populateCompaniesSelect(selectId) {
    const select = document.getElementById(selectId);
    select.innerHTML = '';
    const resp = await fetch(`${API_BASE}/companies.php?active_only=true`, { credentials: 'include' });
    if (!resp.ok) return;
    const data = await resp.json();
    const opts = (data.companies || []).map(c => `<option value="${c.id}">${c.name}${c.code ? ' (' + c.code + ')' : ''}</option>`).join('');
    select.innerHTML = opts;
}

function openWarehouseModal(id = null) {
    document.getElementById('warehouseForm').reset();
    document.getElementById('warehouseId').value = '';
    document.getElementById('warehouseModalTitle').textContent = id ? 'Editar Armazém' : 'Novo Armazém';
    populateCompaniesSelect('warehouseCompany');
    document.getElementById('warehouseModal').classList.remove('hidden');
    if (id) {
        fetch(`${API_BASE}/warehouses.php?id=${id}`, { credentials: 'include' })
            .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch { throw new Error('parse'); } })
            .then(data => {
                if (data.success && data.warehouse) {
                    const w = data.warehouse;
                    document.getElementById('warehouseId').value = w.id;
                    document.getElementById('warehouseCompany').value = w.company_id;
                    document.getElementById('warehouseName').value = w.name || '';
                    document.getElementById('warehouseCode').value = w.code || '';
                    document.getElementById('warehouseAddress').value = w.address || '';
                    document.getElementById('warehouseLocation').value = w.location || '';
                    document.getElementById('warehouseActive').checked = !!w.is_active;
                }
            }).catch(() => showToast('Erro ao carregar armazém', 'error'));
    }
}

function closeWarehouseModal() {
    document.getElementById('warehouseModal').classList.add('hidden');
}

document.addEventListener('submit', (e) => {
    if (e.target && e.target.id === 'warehouseForm') {
        e.preventDefault();
        saveWarehouse();
    }
});

async function saveWarehouse() {
    const id = document.getElementById('warehouseId').value;
    const payload = {
        company_id: parseInt(document.getElementById('warehouseCompany').value),
        name: document.getElementById('warehouseName').value,
        code: document.getElementById('warehouseCode').value,
        address: document.getElementById('warehouseAddress').value,
        location: document.getElementById('warehouseLocation').value,
        is_active: document.getElementById('warehouseActive').checked
    };
    if (id) payload.id = parseInt(id);
    try {
        showLoading();
        const method = id ? 'PUT' : 'POST';
        const resp = await fetch(`${API_BASE}/warehouses.php`, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        hideLoading();
        if (data.success) {
            showToast('Armazém guardado com sucesso');
            closeWarehouseModal();
            loadWarehouses();
        } else {
            showToast(data.message || 'Erro ao guardar armazém', 'error');
        }
    } catch (e) {
        hideLoading();
        showToast('Erro ao guardar armazém', 'error');
    }
}

async function deleteWarehouse(id) {
    if (!confirm('Eliminar este armazém?')) return;
    try {
        showLoading();
        const resp = await fetch(`${API_BASE}/warehouses.php?id=${id}`, { 
            method: 'DELETE',
            credentials: 'include'
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        hideLoading();
        if (data.success) { showToast('Armazém eliminado'); loadWarehouses(); } else { showToast(data.message || 'Erro', 'error'); }
    } catch {
        hideLoading();
        showToast('Erro ao eliminar armazém', 'error');
    }
}

// Abrir Modal de Artigo
async function openItemModal(itemId = null) {
    const modal = document.getElementById('itemModal');
    const form = document.getElementById('itemForm');
    const modalTitle = document.getElementById('itemModalTitle');
    
    // Limpar formulário
    form.reset();
    document.getElementById('itemId').value = '';
    
    // Carregar categorias no select
    await loadCategoriesForSelect();
    
    if (itemId) {
        // Editar artigo existente
        modalTitle.textContent = 'Editar Artigo';
        try {
            showLoading();
            const response = await fetch(`${API_BASE}/items.php?id=${itemId}`, {
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Erro ao fazer parse do JSON:', parseError);
                hideLoading();
                showToast('Erro ao carregar artigo', 'error');
                return;
            }
            
            hideLoading();
            
            if (data.success && data.item) {
                const item = data.item;
                document.getElementById('itemId').value = item.id;
                document.getElementById('itemBarcode').value = item.barcode;
                document.getElementById('itemName').value = item.name;
                document.getElementById('itemDescription').value = item.description || '';
                document.getElementById('itemCategory').value = item.category_id || '';
                document.getElementById('itemQuantity').value = item.quantity || 0;
                document.getElementById('itemMinQuantity').value = item.min_quantity || 0;
                document.getElementById('itemPrice').value = item.unit_price || 0;
                document.getElementById('itemLocation').value = item.location || '';
                document.getElementById('itemSupplier').value = item.supplier || '';
            }
        } catch (error) {
            hideLoading();
            showToast('Erro ao carregar artigo', 'error');
            console.error(error);
        }
    } else {
        // Novo artigo
        modalTitle.textContent = 'Novo Artigo';
    }
    
    modal.classList.remove('hidden');
}

// Fechar Modal de Artigo
function closeItemModal() {
    document.getElementById('itemModal').classList.add('hidden');
    document.getElementById('itemForm').reset();
}

// Fechar Card de Informações do Item
function closeItemInfo() {
    const itemInfoCard = document.getElementById('itemInfoCard');
    if (itemInfoCard) {
        itemInfoCard.classList.add('hidden');
    }
    
    // Limpar dados
    currentBarcode = null;
    currentItemId = null;
    
    // Limpar campos
    const manualBarcodeField = document.getElementById('itemCardManualBarcode');
    const countedQuantityField = document.getElementById('countedQuantity');
    if (manualBarcodeField) manualBarcodeField.value = '';
    if (countedQuantityField) countedQuantityField.value = '0';
}

// Guardar Artigo
async function saveItem(e) {
    e.preventDefault();
    
    const itemId = document.getElementById('itemId').value;
    const barcode = document.getElementById('itemBarcode').value;
    const name = document.getElementById('itemName').value;
    
    // Validações
    const barcodeValidation = validateBarcode(barcode);
    if (!barcodeValidation.valid) {
        showToast(barcodeValidation.message, 'error');
        return;
    }
    
    const nameValidation = validateName(name);
    if (!nameValidation.valid) {
        showToast(nameValidation.message, 'error');
        return;
    }
    
    const itemData = {
        barcode: barcode,
        name: name,
        description: document.getElementById('itemDescription').value,
        category_id: document.getElementById('itemCategory').value || null,
        quantity: parseInt(document.getElementById('itemQuantity').value) || 0,
        min_quantity: parseInt(document.getElementById('itemMinQuantity').value) || 0,
        unit_price: parseFloat(document.getElementById('itemPrice').value) || 0,
        location: document.getElementById('itemLocation').value,
        supplier: document.getElementById('itemSupplier').value
    };
    
    if (itemId) {
        itemData.id = parseInt(itemId);
    }
    
    try {
        showLoading();
        const method = itemId ? 'PUT' : 'POST';
        
        const response = await fetch(`${API_BASE}/items.php`, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(itemData),
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast(itemId ? 'Artigo atualizado com sucesso' : 'Artigo criado com sucesso');
            closeItemModal();
            loadItems(currentItemsPage, currentItemsSearch);
        } else {
            showToast(data.message || 'Erro ao guardar artigo', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao guardar artigo', 'error');
        console.error(error);
    }
}

// Editar Artigo
function editItem(itemId) {
    openItemModal(itemId);
}

// Deletar Artigo
async function deleteItem(itemId) {
    if (!confirm('Tem certeza que deseja eliminar este artigo?')) {
        return;
    }
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/items.php?id=${itemId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast('Artigo eliminado com sucesso');
            loadItems(currentItemsPage, currentItemsSearch);
            loadDashboard(); // Atualizar dashboard
        } else {
            showToast(data.message || 'Erro ao eliminar artigo', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao eliminar artigo', 'error');
        console.error(error);
    }
}

// Carregar Categorias para Select
async function loadCategoriesForSelect() {
    try {
        const response = await fetch(`${API_BASE}/categories.php`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            return;
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            return;
        }
        
        if (data.success && data.categories) {
            const select = document.getElementById('itemCategory');
            const currentValue = select.value;
            select.innerHTML = '<option value="">Selecione...</option>';
            
            data.categories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.name;
                select.appendChild(option);
            });
            
            select.value = currentValue;
        }
    } catch (error) {
        console.error('Erro ao carregar categorias:', error);
    }
}

// ==========================================
// Categories Management Functions
// ==========================================

// Carregar Categorias
async function loadCategories() {
    try {
        const search = document.getElementById('categorySearch')?.value || '';
        currentCategoriesSearch = search;
        
        const params = new URLSearchParams();
        if (search) {
            params.append('search', search);
        }
        
        const url = `${API_BASE}/categories.php${params.toString() ? '?' + params.toString() : ''}`;
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            showToast('Erro ao carregar categorias', 'error');
            return;
        }
        
        if (data.success) {
            const categoriesList = document.getElementById('categoriesList');
            if (!categoriesList) return;
            
            categoriesList.innerHTML = '';
            
            if (data.categories && data.categories.length > 0) {
                data.categories.forEach(category => {
                    const categoryCard = document.createElement('div');
                    categoryCard.className = 'bg-gray-50 rounded-lg p-4 border border-gray-200';
                    categoryCard.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-bold text-lg">${category.name}</h4>
                                <p class="text-sm text-gray-600 mt-1">${category.description || 'Sem descrição'}</p>
                                <p class="text-xs text-gray-500 mt-2">${category.items_count || 0} artigo(s)</p>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <button onclick="editCategory(${category.id})" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    Editar
                                </button>
                                <button onclick="deleteCategory(${category.id})" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    `;
                    categoriesList.appendChild(categoryCard);
                });
            } else {
                categoriesList.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhuma categoria encontrada</p>';
            }
        }
    } catch (error) {
        showToast('Erro ao carregar categorias', 'error');
        console.error(error);
    }
}

// Abrir Modal de Categoria
async function openCategoryModal(categoryId = null) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const modalTitle = document.getElementById('categoryModalTitle');
    
    if (!modal || !form || !modalTitle) {
        console.warn('Elementos de categoria não encontrados');
        return;
    }
    
    // Limpar formulário
    form.reset();
    const categoryIdEl = document.getElementById('categoryId');
    if (categoryIdEl) categoryIdEl.value = '';
    
    if (categoryId) {
        // Editar categoria existente
        modalTitle.textContent = 'Editar Categoria';
        try {
            showLoading();
            const response = await fetch(`${API_BASE}/categories.php?id=${categoryId}`, {
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Erro ao fazer parse do JSON:', parseError);
                hideLoading();
                showToast('Erro ao carregar categoria', 'error');
                return;
            }
            
            hideLoading();
            
            if (data.success && data.category) {
                const category = data.category;
                const categoryIdEl = document.getElementById('categoryId');
                const categoryNameEl = document.getElementById('categoryName');
                const categoryDescriptionEl = document.getElementById('categoryDescription');
                if (categoryIdEl) categoryIdEl.value = category.id;
                if (categoryNameEl) categoryNameEl.value = category.name;
                if (categoryDescriptionEl) categoryDescriptionEl.value = category.description || '';
            }
        } catch (error) {
            hideLoading();
            showToast('Erro ao carregar categoria', 'error');
            console.error(error);
        }
    } else {
        // Nova categoria
        modalTitle.textContent = 'Nova Categoria';
    }
    
    modal.classList.remove('hidden');
}

// Fechar Modal de Categoria
function closeCategoryModal() {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    if (modal) modal.classList.add('hidden');
    if (form) form.reset();
}

// Guardar Categoria
async function saveCategory(e) {
    e.preventDefault();
    
    const categoryIdEl = document.getElementById('categoryId');
    const categoryNameEl = document.getElementById('categoryName');
    const categoryDescriptionEl = document.getElementById('categoryDescription');
    
    if (!categoryIdEl || !categoryNameEl || !categoryDescriptionEl) {
        showToast('Elementos de categoria não encontrados', 'error');
        return;
    }
    
    const categoryId = categoryIdEl.value;
    const name = categoryNameEl.value;
    
    // Validação
    const nameValidation = validateName(name);
    if (!nameValidation.valid) {
        showToast(nameValidation.message, 'error');
        return;
    }
    
    const categoryData = {
        name: name,
        description: categoryDescriptionEl.value
    };
    
    if (categoryId) {
        categoryData.id = parseInt(categoryId);
    }
    
    try {
        showLoading();
        const method = categoryId ? 'PUT' : 'POST';
        
        const response = await fetch(`${API_BASE}/categories.php`, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(categoryData),
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast(categoryId ? 'Categoria atualizada com sucesso' : 'Categoria criada com sucesso');
            closeCategoryModal();
            loadCategories();
            loadDashboard(); // Atualizar dashboard
        } else {
            showToast(data.message || 'Erro ao guardar categoria', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao guardar categoria', 'error');
        console.error(error);
    }
}

// Editar Categoria
function editCategory(categoryId) {
    openCategoryModal(categoryId);
}

// Deletar Categoria
async function deleteCategory(categoryId) {
    if (!confirm('Tem certeza que deseja eliminar esta categoria? Esta ação não pode ser desfeita se não houver artigos associados.')) {
        return;
    }
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/categories.php?id=${categoryId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast('Categoria eliminada com sucesso');
            loadCategories();
            loadDashboard(); // Atualizar dashboard
        } else {
            showToast(data.message || 'Erro ao eliminar categoria', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao eliminar categoria', 'error');
        console.error(error);
    }
}

// ==========================================
// Stock History Functions
// ==========================================

// currentHistoryPage já declarado no topo do arquivo

// Carregar Histórico de Movimentações
async function loadStockHistory(page = 1) {
    try {
        showLoading();
        currentHistoryPage = page;
        
        const params = new URLSearchParams({
            page: page,
            limit: 20
        });
        
        const type = document.getElementById('historyType')?.value;
        const dateFrom = document.getElementById('historyDateFrom')?.value;
        const dateTo = document.getElementById('historyDateTo')?.value;
        
        if (type) {
            params.append('type', type);
        }
        if (dateFrom) {
            params.append('date_from', dateFrom);
        }
        if (dateTo) {
            params.append('date_to', dateTo);
        }
        
        const response = await fetch(`${API_BASE}/stock_history.php?${params.toString()}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao carregar histórico', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            const historyList = document.getElementById('historyList');
            historyList.innerHTML = '';
            
            if (data.movements && data.movements.length > 0) {
                data.movements.forEach(movement => {
                    const movementCard = document.createElement('div');
                    const typeColors = {
                        'entrada': 'bg-green-100 text-green-800 border-green-300',
                        'saida': 'bg-red-100 text-red-800 border-red-300',
                        'ajuste': 'bg-yellow-100 text-yellow-800 border-yellow-300',
                        'transferencia': 'bg-blue-100 text-blue-800 border-blue-300'
                    };
                    const colorClass = typeColors[movement.movement_type] || 'bg-gray-100 text-gray-800 border-gray-300';
                    
                    movementCard.className = `bg-white rounded-lg p-4 border-l-4 ${colorClass.split(' ')[2]} border`;
                    movementCard.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <span class="px-2 py-1 rounded text-xs font-semibold ${colorClass}">
                                        ${movement.movement_type_label}
                                    </span>
                                    <span class="font-bold ${colorClass.split(' ')[0]}">
                                        ${movement.quantity > 0 ? '+' : ''}${movement.quantity}
                                    </span>
                                </div>
                                <h4 class="font-bold text-lg">${movement.item_name}</h4>
                                <p class="text-sm text-gray-600">Código: ${movement.barcode}</p>
                                ${movement.reason ? `<p class="text-xs text-gray-500 mt-1">${movement.reason}</p>` : ''}
                                <p class="text-xs text-gray-500 mt-2">
                                    Por: ${movement.user_name || 'Sistema'} | ${movement.formatted_date}
                                </p>
                            </div>
                        </div>
                    `;
                    historyList.appendChild(movementCard);
                });
                
                // Paginação
                if (data.pagination && data.pagination.pages > 1) {
                    const paginationDiv = document.getElementById('historyPagination');
                    paginationDiv.innerHTML = '';
                    
                    for (let i = 1; i <= data.pagination.pages; i++) {
                        const btn = document.createElement('button');
                        btn.className = `px-4 py-2 rounded-lg ${i === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'}`;
                        btn.textContent = i;
                        btn.onclick = () => loadStockHistory(i);
                        paginationDiv.appendChild(btn);
                    }
                } else {
                    document.getElementById('historyPagination').innerHTML = '';
                }
            } else {
                historyList.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhuma movimentação encontrada</p>';
            }
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao carregar histórico', 'error');
        console.error(error);
    }
}

// ==========================================
// Users Management Functions (Admin Only)
// ==========================================

// currentUsersPage e currentUsersSearch já declarados no topo do arquivo

// Carregar Utilizadores
async function loadUsers(page = 1, search = '') {
    try {
        showLoading();
        currentUsersPage = page;
        currentUsersSearch = search || document.getElementById('usersSearch')?.value || '';
        
        const params = new URLSearchParams({
            page: page,
            limit: 20
        });
        
        if (currentUsersSearch) {
            params.append('search', currentUsersSearch);
        }
        
        const response = await fetch(`${API_BASE}/users.php?${params.toString()}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao carregar utilizadores', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            const usersList = document.getElementById('usersList');
            usersList.innerHTML = '';
            
            if (data.users && data.users.length > 0) {
                data.users.forEach(user => {
                    const userCard = document.createElement('div');
                    userCard.className = 'bg-gray-50 rounded-lg p-4 border border-gray-200';
                    userCard.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <h4 class="font-bold text-lg">${user.username}</h4>
                                    <span class="px-2 py-1 rounded text-xs font-semibold ${
                                        user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'
                                    }">
                                        ${user.role === 'admin' ? 'Administrador' : 'Operador'}
                                    </span>
                                    ${!user.is_active ? '<span class="px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-800">Inativo</span>' : ''}
                                </div>
                                <p class="text-sm text-gray-600 mt-1">${user.email}</p>
                                <p class="text-xs text-gray-500 mt-2">
                                    Criado: ${new Date(user.created_at).toLocaleDateString('pt-PT')}
                                </p>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <button onclick="editUser(${user.id})" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    Editar
                                </button>
                                <button onclick="deleteUser(${user.id})" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    `;
                    usersList.appendChild(userCard);
                });
                
                // Paginação
                if (data.pagination && data.pagination.pages > 1) {
                    const paginationDiv = document.getElementById('usersPagination');
                    paginationDiv.innerHTML = '';
                    
                    for (let i = 1; i <= data.pagination.pages; i++) {
                        const btn = document.createElement('button');
                        btn.className = `px-4 py-2 rounded-lg ${i === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'}`;
                        btn.textContent = i;
                        btn.onclick = () => loadUsers(i);
                        paginationDiv.appendChild(btn);
                    }
                } else {
                    document.getElementById('usersPagination').innerHTML = '';
                }
            } else {
                usersList.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhum utilizador encontrado</p>';
            }
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao carregar utilizadores', 'error');
        console.error(error);
    }
}

// Abrir Modal de Utilizador
async function openUserModal(userId = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const modalTitle = document.getElementById('userModalTitle');
    const passwordRequired = document.getElementById('passwordRequired');
    
    // Limpar formulário
    form.reset();
    document.getElementById('userId').value = '';
    if (passwordRequired) {
        passwordRequired.textContent = '*';
    }
    const passwordField = document.getElementById('userPassword');
    if (passwordField) {
        passwordField.required = true;
    }
    
    if (userId) {
        // Editar utilizador existente
        modalTitle.textContent = 'Editar Utilizador';
        if (passwordRequired) {
            passwordRequired.textContent = '';
        }
        if (passwordField) {
            passwordField.required = false;
        }
        
        try {
            showLoading();
            const response = await fetch(`${API_BASE}/users.php?id=${userId}`, {
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Erro ao fazer parse do JSON:', parseError);
                hideLoading();
                showToast('Erro ao carregar utilizador', 'error');
                return;
            }
            
            hideLoading();
            
            if (data.success && data.user) {
                const user = data.user;
                document.getElementById('userId').value = user.id;
                document.getElementById('userUsername').value = user.username;
                document.getElementById('userEmail').value = user.email;
                document.getElementById('userRole').value = user.role;
                document.getElementById('userIsActive').checked = user.is_active == 1;
            }
        } catch (error) {
            hideLoading();
            showToast('Erro ao carregar utilizador', 'error');
            console.error(error);
        }
    } else {
        // Novo utilizador
        modalTitle.textContent = 'Novo Utilizador';
    }
    
    modal.classList.remove('hidden');
}

// Fechar Modal de Utilizador
function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('userForm').reset();
}

// Guardar Utilizador
async function saveUser(e) {
    e.preventDefault();
    
    const userId = document.getElementById('userId').value;
    const username = document.getElementById('userUsername').value;
    const email = document.getElementById('userEmail').value;
    const password = document.getElementById('userPassword').value;
    const role = document.getElementById('userRole').value;
    const isActive = document.getElementById('userIsActive').checked;
    
    // Validações
    if (!username || !email) {
        showToast('Username e email são obrigatórios', 'error');
        return;
    }
    
    if (!userId && !password) {
        showToast('Password é obrigatória para novos utilizadores', 'error');
        return;
    }
    
    // Validar email (validação simples no cliente)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showToast('Email inválido', 'error');
        return;
    }
    
    const userData = {
        username: username,
        email: email,
        role: role,
        is_active: isActive
    };
    
    if (userId) {
        userData.id = parseInt(userId);
        if (password) {
            userData.password = password;
        }
    } else {
        userData.password = password;
    }
    
    try {
        showLoading();
        const method = userId ? 'PUT' : 'POST';
        
        const response = await fetch(`${API_BASE}/users.php`, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData),
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast(userId ? 'Utilizador atualizado com sucesso' : 'Utilizador criado com sucesso');
            closeUserModal();
            loadUsers(currentUsersPage, currentUsersSearch);
        } else {
            showToast(data.message || 'Erro ao guardar utilizador', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao guardar utilizador', 'error');
        console.error(error);
    }
}

// Editar Utilizador
function editUser(userId) {
    openUserModal(userId);
}

// Deletar Utilizador
async function deleteUser(userId) {
    if (!confirm('Tem certeza que deseja eliminar este utilizador?')) {
        return;
    }
    
    try {
        showLoading();
        const response = await fetch(`${API_BASE}/users.php?id=${userId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            hideLoading();
            showToast('Erro ao processar resposta', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.success) {
            showToast('Utilizador eliminado com sucesso');
            loadUsers(currentUsersPage, currentUsersSearch);
        } else {
            showToast(data.message || 'Erro ao eliminar utilizador', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao eliminar utilizador', 'error');
        console.error(error);
    }
}

// Tornar funções disponíveis globalmente
window.loadSessionInfo = loadSessionInfo;
window.closeSessionDetails = closeSessionDetails;
window.loadDashboard = loadDashboard;
window.loadItems = loadItems;
window.editItem = editItem;
window.deleteItem = deleteItem;
window.openItemModal = openItemModal;
window.closeItemModal = closeItemModal;
window.closeItemInfo = closeItemInfo;
window.editCategory = editCategory;
window.deleteCategory = deleteCategory;
window.openCategoryModal = openCategoryModal;
window.closeCountSetupModal = closeCountSetupModal;
window.confirmCountSetup = confirmCountSetup;
window.closeCategoryModal = closeCategoryModal;
window.loadStockHistory = loadStockHistory;
window.loadUsers = loadUsers;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.openUserModal = openUserModal;
window.closeUserModal = closeUserModal;
window.openCompanyModal = openCompanyModal;
window.closeCompanyModal = closeCompanyModal;
window.deleteCompany = deleteCompany;
window.openWarehouseModal = openWarehouseModal;
window.closeWarehouseModal = closeWarehouseModal;
window.deleteWarehouse = deleteWarehouse;

// ==========================================
// Export Functions
// ==========================================

// Exportar Relatório
function exportReport(reportType = null, format = null) {
    const selectedType = reportType || document.getElementById('reportType')?.value || 'items';
    const selectedFormat = format || document.getElementById('exportFormat')?.value || 'csv';
    
    // Parâmetros adicionais baseados no tipo
    let url = `${API_BASE}/export_reports.php?type=${selectedType}&format=${selectedFormat}`;
    
    // Adicionar filtros para movimentações se aplicável
    if (selectedType === 'movements') {
        const historyType = document.getElementById('historyType')?.value;
        const dateFrom = document.getElementById('historyDateFrom')?.value;
        const dateTo = document.getElementById('historyDateTo')?.value;
        
        if (historyType) {
            url += `&movement_type=${historyType}`;
        }
        if (dateFrom) {
            url += `&date_from=${dateFrom}`;
        }
        if (dateTo) {
            url += `&date_to=${dateTo}`;
        }
    }
    
    // Abrir em nova janela para download
    window.open(url, '_blank');
    
    // Mostrar mensagem de sucesso
    const exportResult = document.getElementById('exportResult');
    if (exportResult) {
        exportResult.classList.remove('hidden');
        exportResult.className = 'mt-4 p-3 bg-green-50 rounded-lg text-green-800 text-sm';
        exportResult.textContent = 'Relatório sendo exportado... Verifique os seus downloads.';
        
        setTimeout(() => {
            exportResult.classList.add('hidden');
        }, 3000);
    } else {
        showToast('Relatório sendo exportado...', 'success');
    }
}

// Tornar função disponível globalmente
window.exportReport = exportReport;

