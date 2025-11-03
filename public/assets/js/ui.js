import { views, elements } from './state.js';

export function showView(viewName) {
    for (const key in views) {
        if (views[key]) {
            views[key].classList.add('hidden');
        }
    }
    if (views[viewName]) {
        views[viewName].classList.remove('hidden');
    }
}

export function showStatus(message, type = 'info') {
    elements.statusMessage.textContent = message;
    elements.statusMessage.className = `status-${type}`;
    setTimeout(() => {
        elements.statusMessage.textContent = '';
        elements.statusMessage.className = '';
    }, 5000);
}

export function populateDbList(databases, onSelect) {
    elements.dbList.innerHTML = ''; // Limpiar lista
    databases.forEach(dbName => {
        const li = document.createElement('li');
        li.textContent = dbName;
        li.addEventListener('click', () => onSelect(dbName));
        elements.dbList.appendChild(li);
    });
}