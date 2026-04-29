/**
 */
import { getEmployeeList } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class EmployeeModule {
    constructor() {
        this.containerId = 'employees';
        this.isInitialized = false;
        this.editingId = null;
        this.allEmployees = [];
        this.availableCategories = [];
        this.selectedCategory = null;
    }

    init() {
        if (this.isInitialized) {
            this.loadEmployees();
            this.loadCategories(); // Recargar por si acaso
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadCategories();
        this.loadEmployees();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="employees-layout">
                <div class="table-header">
                    <h2>Gestión de Empleados</h2>
                    <div class="table-controls" style="display:flex; align-items:center; gap:10px;">
                        <input type="text" style="display:none" aria-hidden="true">
                        <input type="search" id="emp-search-input" name="e_find" placeholder="Buscar por nombre, DNI o email..."
                            style="padding:8px 12px; border:2px solid #1b1b1b; border-radius:8px; font-family:'Satoshi',sans-serif; font-weight:500; font-size:0.95rem; outline:none; width:280px; box-shadow:2px 2px 0px rgba(0,0,0,0.1); transition:all 0.2s; align-self:stretch;">
                        <button id="emp-categories-btn" class="btn btn-secondary"><i class="ph ph-tag"></i> Categorías</button>
                        <button id="emp-create-btn" class="btn btn-primary">+ Nuevo Empleado</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto; background: #f9f9f9;">
                    <div id="emp-list-body" class="emp-grid">
                        </div>
                </div>

                <div id="create-employee-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 600px; max-width: 95%;">
                        <div class="modal-header">
                            <h3 id="modal-emp-title">Registrar Nuevo Empleado</h3>
                            <button class="modal-close-btn" id="close-emp-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="create-emp-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                
                                <div style="grid-column: span 2;">
                                    <label class="micro-label">Nombre Completo *</label>
                                    <input type="text" id="emp-name" class="rustic-input" style="width:100%;" required placeholder="Ej: María Gonzalez">
                                </div>

                                <div>
                                    <label class="micro-label">DNI / Identificación</label>
                                    <input type="text" id="emp-dni" class="rustic-input" style="width:100%;" placeholder="Solo números">
                                </div>
                                <div>
                                    <label class="micro-label">Teléfono</label>
                                    <input type="text" id="emp-phone" class="rustic-input" style="width:100%;" placeholder="Ej: 11 1234 5678">
                                </div>

                                <div style="grid-column: span 2;">
                                    <label class="micro-label">Correo Electrónico</label>
                                    <input type="email" id="emp-email" class="rustic-input" style="width:100%;" placeholder="contacto@empleado.com">
                                </div>

                                <div style="grid-column: span 2;">
                                    <label class="micro-label">Categoría / Grupo</label>
                                    <select id="emp-category" class="rustic-select" style="width:100%;">
                                        <option value="">Sin Categoría</option>
                                    </select>
                                </div>

                                <!-- Contenedor para campos dinámicos -->
                                <div id="dynamic-fields-container" style="grid-column: span 2; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                </div>

                                <div style="grid-column: span 2; text-align: right; margin-top: 10px; border-top: 1px solid #eee; padding-top: 15px;">
                                    <button type="button" id="cancel-emp-btn" class="btn btn-secondary">Cancelar</button>
                                    <button type="submit" id="submit-emp-btn" class="btn btn-primary">Guardar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Modal de Gestión de Categorías -->
                <div id="manage-categories-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1100;">
                    <div class="modal-content" style="width: 500px; max-width: 95%;">
                        <div class="modal-header">
                            <h3>Gestionar Categorías</h3>
                            <button class="modal-close-btn" id="close-cat-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <div id="cat-list" style="margin-bottom: 20px; max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 8px;">
                                <!-- Lista de categorías -->
                            </div>
                            <div style="border-top: 2px solid #1b1b1b; padding-top: 15px;">
                                <h4 id="cat-editor-title">Crear Nueva Categoría</h4>
                                <div style="margin-bottom: 10px;">
                                    <label class="micro-label">Nombre de la Categoría</label>
                                    <input type="text" id="cat-name" class="rustic-input" style="width:100%;" placeholder="Ej: Vendedores">
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label class="micro-label">Campos Personalizados (Separados por coma)</label>
                                    <input type="text" id="cat-fields" class="rustic-input" style="width:100%;" placeholder="Ej: Domicilio, Seniority, Vehículo">
                                    <small style="color: #666; font-size: 0.75rem;">Estos campos aparecerán en el formulario del empleado.</small>
                                </div>
                                <div style="text-align: right; margin-top: 15px;">
                                    <button id="cancel-cat-edit" class="btn btn-secondary hidden">Cancelar Edición</button>
                                    <button id="save-cat-btn" class="btn btn-primary">Guardar Categoría</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de Detalle Personal -->
                <div id="employee-detail-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1200;">
                    <div class="modal-content" style="width: 700px; max-width: 95%;">
                        <div class="emp-detail-header">
                            <div class="emp-detail-avatar" id="det-emp-avatar">U</div>
                            <div class="emp-detail-info">
                                <h2 id="det-emp-name">Nombre Empleado</h2>
                                <p id="det-emp-cat"><i class="ph ph-tag"></i> Categoría</p>
                                <p id="det-emp-contact"><i class="ph ph-envelope"></i> email | <i class="ph ph-phone"></i> tel</p>
                            </div>
                            <button class="modal-close-btn" id="close-emp-detail-modal" style="margin-left: auto; align-self: flex-start;">&times;</button>
                        </div>
                        <div class="emp-detail-body">
                            <h4 style="margin-top: 0; margin-bottom: 15px;">Detalles Adicionales</h4>
                            <div class="emp-detail-custom-grid" id="det-emp-custom-grid">
                                <!-- Campos dinámicos aquí -->
                            </div>
                            
                            <div class="emp-sales-history">
                                <h4 style="margin-bottom: 15px;"><i class="ph-bold ph-receipt"></i> Historial de Ventas</h4>
                                <div class="emp-sales-list" id="det-emp-sales-list">
                                    <p style="color: #888; text-align: center;">Cargando ventas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        `;
    }

    attachEvents() {
        const modal = document.getElementById('create-employee-modal');
        const form = document.getElementById('create-emp-form');

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            form.reset();
            this.editingId = null; // Limpiamos ID de edición
        };

        document.getElementById('emp-create-btn')?.addEventListener('click', () => {
            this.editingId = null;
            document.getElementById('modal-emp-title').textContent = "Registrar Nuevo Empleado";
            document.getElementById('submit-emp-btn').textContent = "Guardar";
            form.reset();
            document.getElementById('dynamic-fields-container').innerHTML = '';
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        });

        document.getElementById('close-emp-modal')?.addEventListener('click', closeModal);
        document.getElementById('cancel-emp-btn')?.addEventListener('click', closeModal);

        form?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        document.getElementById('emp-list-body')?.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.btn-edit-emp');
            const deleteBtn = e.target.closest('.btn-delete-emp');
            const customContainer = e.target.closest('.emp-custom-container');

            if (editBtn) {
                const empData = JSON.parse(editBtn.dataset.employee);
                this.openEditModal(empData);
                return;
            }
            if (deleteBtn) {
                const empId = deleteBtn.dataset.id;
                this.deleteEmployee(empId);
                return;
            }
            if (customContainer) {
                const empId = customContainer.dataset.id;
                this.openDetailModal(empId);
            }
        });

        document.getElementById('emp-search-input')?.addEventListener('input', (e) => {
            this.filterEmployees(e.target.value);
        });

        // Eventos de Categorías
        document.getElementById('emp-categories-btn')?.addEventListener('click', () => {
            this.openCategoryModal();
        });

        document.getElementById('close-cat-modal')?.addEventListener('click', () => {
            document.getElementById('manage-categories-modal').style.display = 'none';
        });

        document.getElementById('save-cat-btn')?.addEventListener('click', () => {
            this.handleSaveCategory();
        });

        document.getElementById('emp-category')?.addEventListener('change', (e) => {
            this.renderDynamicFields(e.target.value);
        });

        document.getElementById('cancel-cat-edit')?.addEventListener('click', () => {
            this.resetCategoryEditor();
        });

        document.getElementById('close-emp-detail-modal')?.addEventListener('click', () => {
            const modal = document.getElementById('employee-detail-modal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        });
    }

    async openCategoryModal() {
        const modal = document.getElementById('manage-categories-modal');
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        await this.loadCategories();
    }

    async loadCategories() {
        try {
            const response = await fetch('/api/employees/categories/get-all.php');
            const result = await response.json();
            if (result.success) {
                this.availableCategories = result.categories;
                this.renderCategoryList();
                this.updateCategorySelect();
            }
        } catch (e) {
            console.error("Error al cargar categorías:", e);
        }
    }

    renderCategoryList() {
        const list = document.getElementById('cat-list');
        if (!list) return;
        if (!this.availableCategories.length) {
            list.innerHTML = '<p style="color:#888; font-size:0.9rem; text-align:center;">No hay categorías creadas.</p>';
            return;
        }
        list.innerHTML = this.availableCategories.map(c => `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #eee;">
                <div>
                    <strong style="font-size:0.95rem;">${c.name}</strong>
                    <div style="font-size:0.75rem; color:#666;">Campos: ${c.fields.join(', ') || 'Ninguno'}</div>
                </div>
                <div style="display:flex; gap:5px;">
                    <button class="btn-icon edit-cat" data-id="${c.id}"><i class="ph ph-pencil-simple"></i></button>
                    <button class="btn-icon delete-cat" data-id="${c.id}"><i class="ph ph-trash" style="color:var(--accent-red)"></i></button>
                </div>
            </div>
        `).join('');

        list.querySelectorAll('.edit-cat').forEach(btn => {
            btn.onclick = () => this.startEditCategory(btn.dataset.id);
        });
        list.querySelectorAll('.delete-cat').forEach(btn => {
            btn.onclick = () => this.deleteCategory(btn.dataset.id);
        });
    }

    updateCategorySelect() {
        const select = document.getElementById('emp-category');
        if (!select) return;
        const currentVal = select.value;
        select.innerHTML = '<option value="">Sin Categoría</option>' +
            this.availableCategories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        select.value = currentVal;
    }

    startEditCategory(id) {
        const cat = this.availableCategories.find(c => c.id == id);
        if (!cat) return;
        this.editingCatId = id;
        document.getElementById('cat-editor-title').textContent = "Editar Categoría";
        document.getElementById('cat-name').value = cat.name;
        document.getElementById('cat-fields').value = cat.fields.join(', ');
        document.getElementById('cancel-cat-edit').classList.remove('hidden');
    }

    resetCategoryEditor() {
        this.editingCatId = null;
        document.getElementById('cat-editor-title').textContent = "Crear Nueva Categoría";
        document.getElementById('cat-name').value = '';
        document.getElementById('cat-fields').value = '';
        document.getElementById('cancel-cat-edit').classList.add('hidden');
    }

    async handleSaveCategory() {
        const name = document.getElementById('cat-name').value;
        const fieldsStr = document.getElementById('cat-fields').value;
        const fields = fieldsStr.split(',').map(f => f.trim()).filter(f => f.length > 0);

        if (!name) { pop_ups.warning("El nombre es obligatorio"); return; }

        const endpoint = this.editingCatId ? '/api/employees/categories/update.php' : '/api/employees/categories/create.php';
        const data = { id: this.editingCatId, name, fields };

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                pop_ups.success(this.editingCatId ? "Categoría actualizada" : "Categoría creada");
                this.resetCategoryEditor();
                await this.loadCategories();
            }
        } catch (e) {
            pop_ups.error("Error al guardar categoría");
        }
    }

    async deleteCategory(id) {
        const confirm = await pop_ups.confirm("Eliminar Categoría", "¿Estás seguro? Los empleados mantendrán sus datos pero ya no estarán asociados a esta categoría.");
        if (!confirm) return;
        try {
            const response = await fetch('/api/employees/categories/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            if (result.success) {
                pop_ups.info("Categoría eliminada");
                await this.loadCategories();
            }
        } catch (e) {
            pop_ups.error("Error al eliminar");
        }
    }

    renderDynamicFields(categoryId, values = {}) {
        const container = document.getElementById('dynamic-fields-container');
        if (!container) return;
        container.innerHTML = '';

        if (!categoryId) return;

        const cat = this.availableCategories.find(c => c.id == categoryId);
        if (!cat || !cat.fields) return;

        cat.fields.forEach(field => {
            const val = values[field] || '';
            const fieldHtml = `
                <div style="grid-column: span 2;">
                    <label class="micro-label">${field}</label>
                    <input type="text" class="rustic-input dynamic-field" data-label="${field}" style="width:100%;" value="${val}" placeholder="Completar ${field}...">
                </div>
            `;
            container.insertAdjacentHTML('beforeend', fieldHtml);
        });
    }

    openEditModal(emp) {
        this.editingId = emp.id;
        document.getElementById('modal-emp-title').textContent = "Editar Empleado";
        document.getElementById('submit-emp-btn').textContent = "Actualizar Cambios";

        document.getElementById('emp-name').value = emp.full_name || '';
        document.getElementById('emp-dni').value = emp.dni || '';
        document.getElementById('emp-phone').value = emp.phone || '';
        document.getElementById('emp-email').value = emp.email || '';
        document.getElementById('emp-category').value = emp.category_id || '';

        this.renderDynamicFields(emp.category_id, emp.custom_data || {});

        const modal = document.getElementById('create-employee-modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    async handleFormSubmit() {
        const btn = document.getElementById('submit-emp-btn');
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = "Procesando...";

        const data = {
            id: this.editingId, // Si es null, es creación
            name: document.getElementById('emp-name').value,
            dni: document.getElementById('emp-dni').value,
            phone: document.getElementById('emp-phone').value,
            email: document.getElementById('emp-email').value,
            category_id: document.getElementById('emp-category').value || null,
            custom_data: {}
        };

        // Recolectar campos dinámicos
        document.querySelectorAll('.dynamic-field').forEach(input => {
            data.custom_data[input.dataset.label] = input.value;
        });

        const endpoint = this.editingId ? '/api/employees/update.php' : '/api/employees/create.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('create-employee-modal').classList.add('hidden');
                document.getElementById('create-employee-modal').style.display = 'none';
                await this.loadEmployees();
                pop_ups.success(this.editingId ? 'Empleado actualizado.' : 'Empleado creado.');
            } else {
                pop_ups.error(result.message || 'Error en la operación');
            }
        } catch (e) {
            console.error(e);
            pop_ups.error("Error de conexión");
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async deleteEmployee(id) {
        const confirmed = await pop_ups.confirm("Eliminar Empleado", "¿Estás seguro? Esta acción no se puede deshacer.");
        if (!confirmed) return;

        try {
            const response = await fetch('/api/employees/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const result = await response.json();

            if (result.success) {
                await this.loadEmployees();
                pop_ups.info("Empleado eliminado.");
            } else {
                pop_ups.error(result.message || "No se pudo eliminar.");
            }
        } catch (e) {
            pop_ups.error("Error de conexión");
        }
    }

    async loadEmployees() {
        const container = document.getElementById('emp-list-body');
        if (!container) return;
        container.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">Cargando...</p>';

        const searchInput = document.getElementById('emp-search-input');
        if (searchInput) searchInput.value = '';

        try {
            const data = await getEmployeeList();
            if (!data.success || !data.employees.length) {
                this.allEmployees = [];
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align:center; padding:3rem; color:#888;">
                        <i class="ph ph-users" style="font-size: 3rem; margin-bottom: 10px;"></i>
                        <p>No hay empleados registrados aún.</p>
                    </div>`;
                return;
            }

            this.allEmployees = data.employees;
            this.renderEmployeeCards(this.allEmployees);

        } catch (e) {
            console.error(e);
            container.innerHTML = '<p style="color:red; text-align:center;">Error al cargar lista.</p>';
        }
    }

    renderEmployeeCards(employees) {
        const container = document.getElementById('emp-list-body');
        if (!container) return;

        if (!employees.length) {
            container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:2rem; color:#999;">No se encontraron resultados.</div>`;
            return;
        }

        container.innerHTML = employees.map(e => {
            const empJson = JSON.stringify(e).replace(/"/g, '&quot;');
            const phoneHtml = e.phone ? `<div title="Teléfono"><i class="ph ph-phone"></i> ${e.phone}</div>` : '';
            const emailHtml = e.email ? `<div title="Email"><i class="ph ph-envelope"></i> ${e.email}</div>` : '';
            const dniHtml = e.dni ? `<div title="DNI"><i class="ph ph-identification-card"></i> ${e.dni}</div>` : '';
            const catHtml = e.category_name ? `<div class="emp-tag"><i class="ph ph-tag"></i> ${e.category_name}</div>` : `<div class="emp-tag" style="visibility:hidden; pointer-events:none;"><i class="ph ph-tag"></i>-</div>`;

            // Renderizar el acceso al perfil completo en todas las tarjetas
            let customFieldsHtml = `<div class="emp-custom-field" style="text-align:center; color:#888; padding-top:15px;">Ver perfil y ventas</div>`;

            return `
            <div class="emp-card">
                <div class="emp-card-actions">
                    <button class="btn-icon btn-edit-emp" data-employee="${empJson}" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                    <button class="btn-icon btn-delete-emp" data-id="${e.id}" title="Eliminar"><i class="ph ph-trash" style="color:var(--accent-red)"></i></button>
                </div>
                <div class="emp-avatar">
                    ${e.full_name.charAt(0).toUpperCase()}
                </div>
                <div class="emp-name">${e.full_name}</div>
                ${catHtml}
                <div class="emp-details">
                    ${dniHtml}
                    ${phoneHtml}
                    ${emailHtml}
                </div>
                <div class="emp-custom-container" data-id="${e.id}" title="Ver todos los detalles">
                    ${customFieldsHtml}
                    <div class="emp-hover-overlay"><i class="ph-bold ph-eye"></i></div>
                </div>
                <div class="emp-footer">
                     Registrado: ${new Date(e.created_at).toLocaleDateString()}
                </div>
            </div>
        `}).join('');
    }

    openDetailModal(empId) {
        const emp = this.allEmployees.find(e => e.id == empId);
        if (!emp) return;

        document.getElementById('det-emp-avatar').textContent = emp.full_name.charAt(0).toUpperCase();
        document.getElementById('det-emp-name').textContent = emp.full_name;
        document.getElementById('det-emp-cat').innerHTML = emp.category_name ? `<i class="ph ph-tag"></i> ${emp.category_name}` : `<i class="ph ph-user"></i> Sin categoría`;

        let contactHtml = '';
        if (emp.email) contactHtml += `<i class="ph ph-envelope"></i> ${emp.email} `;
        if (emp.phone) contactHtml += `| <i class="ph ph-phone"></i> ${emp.phone}`;
        if (!emp.email && !emp.phone) contactHtml = 'Sin datos de contacto';
        document.getElementById('det-emp-contact').innerHTML = contactHtml;

        const customGrid = document.getElementById('det-emp-custom-grid');
        customGrid.innerHTML = '';
        if (emp.dni) {
            customGrid.insertAdjacentHTML('beforeend', `<div class="emp-detail-custom-item"><strong>DNI / Identificación</strong>${emp.dni}</div>`);
        }

        if (emp.custom_data && typeof emp.custom_data === 'object') {
            Object.entries(emp.custom_data).forEach(([label, val]) => {
                if (val) {
                    customGrid.insertAdjacentHTML('beforeend', `<div class="emp-detail-custom-item"><strong>${label}</strong>${val}</div>`);
                }
            });
        }

        const modal = document.getElementById('employee-detail-modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';

        this.loadEmployeeSales(emp.id);
    }

    async loadEmployeeSales(empId) {
        const list = document.getElementById('det-emp-sales-list');
        list.innerHTML = '<p style="color: #888; text-align: center;">Cargando ventas...</p>';

        try {
            const { getEmployeeSales } = await import('../api.js');
            const data = await getEmployeeSales(empId);

            if (!data.success || !data.sales || data.sales.length === 0) {
                list.innerHTML = '<p style="padding-top: 20px; color: #888; text-align: center;">No hay ventas registradas para este empleado.</p>';
                return;
            }

            list.innerHTML = data.sales.map(sale => {
                const total = parseFloat(sale.total).toLocaleString('es-AR', { style: 'currency', currency: 'ARS' });
                const date = new Date(sale.created_at).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
                const comm = parseFloat(sale.commission) > 0 
                    ? `<div style="font-size: 0.85rem; color: var(--accent-green); margin-top: 4px;"><i class="ph ph-coins"></i> Comisión ganada: ${parseFloat(sale.commission).toLocaleString('es-AR', { style: 'currency', currency: 'ARS' })}</div>` 
                    : '';
                
                return `
                    <div class="emp-sale-item" style="cursor: pointer;" onclick="if(window.salesModuleInstance) window.salesModuleInstance.showDetails('${sale.id}'); else alert('El módulo de ventas aún no está cargado. Recargue la página e ingrese primero a Ventas.');" title="Ver Ticket de Venta">
                        <div>
                            <strong><i class="ph ph-receipt"></i> Registro de Venta</strong><br>
                            <small style="color: #666;">${date} - Cliente: ${sale.customer_name}</small>
                            ${comm}
                        </div>
                        <div style="font-weight: bold; color: var(--accent-color);">
                            ${total}
                        </div>
                    </div>
                `;
            }).join('');

        } catch (e) {
            console.error("Error cargando ventas:", e);
            list.innerHTML = '<p style="color: red; text-align: center;">Error al cargar ventas.</p>';
        }
    }

    filterEmployees(term) {
        const normalize = (str) => (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const stripNonDigits = (str) => (str || '').replace(/\D/g, '');
        const q = normalize(term);
        const qDigits = stripNonDigits(term);
        if (!q && !qDigits) {
            this.renderEmployeeCards(this.allEmployees);
            return;
        }
        const filtered = this.allEmployees.filter(e => {
            const name = normalize(e.full_name);
            const dni = normalize(e.dni);
            const email = normalize(e.email);
            const phone = stripNonDigits(e.phone);
            if (q && (name.includes(q) || dni.includes(q) || email.includes(q))) return true;
            if (qDigits && (phone.includes(qDigits) || stripNonDigits(e.dni).includes(qDigits))) return true;
            return false;
        });
        this.renderEmployeeCards(filtered);
    }
}

export const employeeModuleInstance = new EmployeeModule();