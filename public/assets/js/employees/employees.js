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
    }

    init() {
        if (this.isInitialized) {
            this.loadEmployees();
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadEmployees();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="employees-layout">
                <div class="table-header">
                    <h2>Gestión de Empleados</h2>
                    <div class="table-controls" style="display:flex; align-items:center; gap:10px;">
                        <input type="text" id="emp-search-input" placeholder="Buscar por nombre, DNI o email..."
                            style="padding:8px 12px; border:2px solid #1b1b1b; border-radius:8px; font-family:'Satoshi',sans-serif; font-weight:500; font-size:0.95rem; outline:none; width:280px; box-shadow:2px 2px 0px rgba(0,0,0,0.1); transition:all 0.2s; align-self:stretch;">
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

                                <div style="grid-column: span 2; text-align: right; margin-top: 10px; border-top: 1px solid #eee; padding-top: 15px;">
                                    <button type="button" id="cancel-emp-btn" class="btn btn-secondary">Cancelar</button>
                                    <button type="submit" id="submit-emp-btn" class="btn btn-primary">Guardar</button>
                                </div>
                            </form>
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

            if (editBtn) {
                const empData = JSON.parse(editBtn.dataset.employee);
                this.openEditModal(empData);
            }
            if (deleteBtn) {
                const empId = deleteBtn.dataset.id;
                this.deleteEmployee(empId);
            }
        });

        document.getElementById('emp-search-input')?.addEventListener('input', (e) => {
            this.filterEmployees(e.target.value);
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
            email: document.getElementById('emp-email').value
        };

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
        if(!container) return;
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
                <div class="emp-details">
                    ${dniHtml}
                    ${phoneHtml}
                    ${emailHtml}
                </div>
                <div class="emp-footer">
                     Registrado: ${new Date(e.created_at).toLocaleDateString()}
                </div>
            </div>
        `}).join('');
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