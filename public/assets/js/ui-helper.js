// assets/js/ui-helper.js

export const ui_helper = {
    // Definimos los tipos de botones para no repetir código
    buttons: {
        dashboard: () => `<a href="/select-db.php" class="btn btn-secondary">Ir al Panel</a>`,
        config: () => `<a href="/configuration.php" class="btn btn-secondary">Configuración</a>`,
        logout: () => `<a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>`,

        // El Dropdown es un componente más complejo
        userDropdown: (extraLinks = []) => {
            const defaultLinks = [
                { label: 'Configuración', href: 'configuration.php', icon: 'ph-gear' },
                { label: 'Soporte', href: 'configuration.php?tab=soporte', icon: 'ph-lifebuoy' }
            ];
            // Unimos los links por defecto con los que pida cada página
            const allLinks = [...defaultLinks, ...extraLinks, { label: 'Cerrar Sesión', href: 'logout.php', icon: 'ph-sign-out' }];

            return `
            <div id="dropdown-container">
                <div class="btn btn-secondary" id="mi-cuenta-btn">Mi Cuenta <i class="ph ph-caret-down"></i></div>
                <div class="flex-column hidden" id="mi-cuenta-dropdown">
                    ${allLinks.map(link => `
                        <a href="${link.href}" class="btn btn-secondary">
                            <i class="${link.icon}"></i> ${link.label}
                        </a>
                    `).join('')}
                </div>
            </div>`;
        }
    },

    /**
     * Renderiza el header según la disposición solicitada
     * @param {string} template - 'dashboard', 'stats', 'config'
     */
    renderHeader(template) {
        const nav = document.getElementById('header-nav');
        if (!nav) return;

        let html = '';

        switch (template) {
            case 'stats':
                html = this.buttons.dashboard() + this.buttons.userDropdown();
                break;
            case 'dashboard':
                html = this.buttons.userDropdown();
                break;
            case 'config':
                html = this.buttons.dashboard() + this.buttons.logout();
                break;
        }

        nav.innerHTML = html;
        this.initDropdownEvents();
    },

    initDropdownEvents() {
        const btn = document.getElementById('mi-cuenta-btn');
        const dropdown = document.getElementById('mi-cuenta-dropdown');

        if (btn && dropdown) {
            btn.onclick = (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
                btn.classList.toggle('clicked');
            };

            document.addEventListener('click', () => {
                dropdown.classList.add('hidden');
                btn.classList.remove('clicked');
            });
        }
    }
};