// Contenedores de las diferentes "vistas" de la aplicación
export const views = {
    loading: document.getElementById('loading-view'),
    login: document.getElementById('login-view'),
    selection: document.getElementById('selection-view'),
    firstTime: document.getElementById('first-time-view'),
    main: document.getElementById('main-app-view'),
    dbData: document.getElementById('db-data-view'),
};

// Elementos interactivos de la interfaz de usuario
export const elements = {
    dbList: document.getElementById('db-list'),
    goToLoginBtn: document.getElementById('go-to-login-btn'),
    createDbBtn: document.getElementById('createDbBtn'),
    dbNameInput: document.getElementById('dbNameInput'),
    columnsInput: document.getElementById('columnsInput'),
    activeDbName: document.getElementById('active-db-name'),
    statusMessage: document.getElementById('status-message'),
    changeDbBtn: document.getElementById('change-db-btn'),
    viewDbBtn: document.getElementById('view-db-btn'),
    backToMainMenuBtn: document.getElementById('back-to-main-menu-btn'),
};