/**
 * public/assets/js/employees/employees.js
 * Módulo de Gestión de Empleados.
 */
import { getEmployeeList, createEmployeeNew } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class EmployeeModule {
    constructor() {
        this.containerId = 'employees';
        this.isInitialized = false;
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
                    <h2>Empleados</h2>
                    <div class="table-controls">
                        <button id="emp-create-btn" class="btn btn-primary">+ Nuevo Empleado</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto; background: #f9f9f9;">
                    <div id="emp-list-body" class="emp-grid">
                        </div>
                </div>

                <div id="create-employee-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 400px; max-width: 90%;">
                        <div class="modal-header">
                            <h3>Nuevo Empleado</h3>
                            <button class="modal-close-btn" id="close-emp-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="create-emp-form">
                                <div style="margin-bottom: 15px;">
                                    <label style="display:block; margin-bottom:5px; font-weight:600;">Nombre Completo</label>
                                    <input type="text" id="emp-name" class="rustic-input" style="width:100%; padding:10px;" required placeholder="Ej: María Gonzalez">
                                </div>
                                <div style="text-align: right;">
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
        // Modal Crear
        document.getElementById('emp-create-btn')?.addEventListener('click', () => {
            document.getElementById('create-emp-form').reset();
            const m = document.getElementById('create-employee-modal'); m.classList.remove('hidden'); m.style.display='flex';
        });
        document.getElementById('close-emp-modal')?.addEventListener('click', () => {
            const m = document.getElementById('create-employee-modal'); m.classList.add('hidden'); m.style.display='none';
        });

        // Submit
        document.getElementById('create-emp-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitEmployee();
        });
    }

    async submitEmployee() {
        const btn = document.getElementById('submit-emp-btn');
        btn.disabled = true;
        btn.textContent = "Guardando...";

        const name = document.getElementById('emp-name').value;

        try {
            const response = await createEmployeeNew(name);
            if (response.success) {
                document.getElementById('create-employee-modal').classList.add('hidden');
                document.getElementById('create-employee-modal').style.display='none';
                await this.loadEmployees();
                pop_ups.success('Empleado registrado');
            } else {
                pop_ups.error(response.message || 'Error');
            }
        } catch (e) { console.error(e); }
        finally { btn.disabled = false; btn.textContent = "Guardar"; }
    }

    async loadEmployees() {
        const container = document.getElementById('emp-list-body');
        if(!container) return;
        container.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">Cargando...</p>';

        try {
            const data = await getEmployeeList();
            if (!data.success || !data.employees.length) {
                container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:2rem; color:#999;">No hay empleados registrados.<br>Agregá uno nuevo para comenzar.</div>';
                return;
            }

            container.innerHTML = data.employees.map(e => `
                <div class="emp-card">
                    <div class="emp-avatar">
                        ${e.full_name.charAt(0).toUpperCase()}
                    </div>
                    <div class="emp-name">${e.full_name}</div>
                    <div class="emp-id">ID: ${e.id}</div>
                    <div style="font-size:0.75rem; color:#aaa; margin-top:10px;">
                        Desde: ${new Date(e.created_at).toLocaleDateString()}
                    </div>
                </div>
            `).join('');

        } catch (e) {
            console.error(e);
            container.innerHTML = '<p style="color:red; text-align:center;">Error al cargar</p>';
        }
    }
}

export const employeeModuleInstance = new EmployeeModule();