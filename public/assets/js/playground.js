/* public/assets/js/playground.js */

document.addEventListener("DOMContentLoaded", () => {
    // 1. Datos iniciales por defecto (maqueta ultra realista basada en la captura de pantalla)
    const defaultProducts = [
        { id: 1, name: "Mesa de Pc en L Minimalista Basic Negro", stock: 20, min_stock: 9, barcode: "779575871702", sale_price: 146293.56, buy_price: 103800.00, general_cat: "Oficina", specific_cat: "Escritorios", brand: "Minimalista" },
        { id: 2, name: "Reloj de Pared Minimalista Minimalista Basic Wengue", stock: 118, min_stock: 14, barcode: "779577680522", sale_price: 100900.00, buy_price: 66400.00, general_cat: "Decoración", specific_cat: "Varios Deco", brand: "Minimalista" },
        { id: 3, name: "Escritorio con Cajonera Minimalista Basic Roble Claro", stock: 68, min_stock: 7, barcode: "779542191768", sale_price: 263200.00, buy_price: 150400.00, general_cat: "Oficina", specific_cat: "Escritorios", brand: "Minimalista" },
        { id: 4, name: "Reloj de Pared Minimalista Eclectic Vintage Wengue", stock: 31, min_stock: 8, barcode: "779857224478", sale_price: 38600.00, buy_price: 22200.00, general_cat: "Decoración", specific_cat: "Varios Deco", brand: "Eclectic Vi" },
        { id: 5, name: "Alfombra Pelo Largo Industrial Loft Crudo", stock: 20, min_stock: 2, barcode: "779929270851", sale_price: 122400.00, buy_price: 81100.00, general_cat: "Textiles", specific_cat: "Blancos y Alfombras", brand: "Industrial L" },
        { id: 6, name: "Diván Relax Línea Nórdica Negro", stock: 96, min_stock: 12, barcode: "779417500086", sale_price: 421200.00, buy_price: 307500.00, general_cat: "Muebles", specific_cat: "Sala de Estar", brand: "Línea Nórdica" },
        { id: 7, name: "Silla Eames Minimalista Basic Crudo", stock: 9, min_stock: 13, barcode: "779408081074", sale_price: 124500.00, buy_price: 78800.00, general_cat: "Muebles", specific_cat: "Sillas y Banquetas", brand: "Minimalista" },
        { id: 8, name: "Sofá Cama Premium Línea Nórdica Gris", stock: 0, min_stock: 13, barcode: "779826047812", sale_price: 790000.00, buy_price: 568400.00, general_cat: "Muebles", specific_cat: "Sala de Estar", brand: "Línea Nórdica" },
        { id: 9, name: "Respaldo de Cama Capitoné Minimalista Basic Blanco", stock: 56, min_stock: 6, barcode: "779712045634", sale_price: 468100.00, buy_price: 289000.00, general_cat: "Muebles", specific_cat: "Dormitorio", brand: "Minimalista" },
        { id: 10, name: "Espejo Circular Decorativo Vittoria Premium Wengue", stock: 32, min_stock: 9, barcode: "779495759667", sale_price: 81600.00, buy_price: 60500.00, general_cat: "Decoración", specific_cat: "Varios Deco", brand: "Vittoria Pre" }
    ];

    let products = [...defaultProducts];
    let nextId = 11;
    
    // Configuración dinámica de columnas del inventario
    const ALL_COLUMNS = [
        { key: 'id', name: 'ID', icon: null, align: 'center' },
        { key: 'name', name: 'Nombre', icon: 'ph-bold ph-article', align: 'left' },
        { key: 'stock', name: 'Stock', icon: 'ph-bold ph-package', align: 'center' },
        { key: 'min_stock', name: 'Stock Mínimo', icon: 'ph-bold ph-folder-simple-minus', align: 'center' },
        { key: 'barcode', name: 'Código de barras', icon: null, align: 'left' },
        { key: 'sale_price', name: 'Precio de Venta', icon: 'ph-bold ph-coin-vertical', align: 'left' },
        { key: 'buy_price', name: 'Precio de compra', icon: 'ph-bold ph-shopping-cart', align: 'left' },
        { key: 'general_cat', name: 'Categoría general', icon: null, align: 'left' },
        { key: 'specific_cat', name: 'Categoría específica', icon: null, align: 'left' },
        { key: 'brand', name: 'Marca', icon: null, align: 'left' },
        { key: 'actions', name: 'Acciones', icon: null, align: 'center' }
    ];

    // Por defecto ocultamos las columnas secundarias para calzar con la captura de pantalla
    let hiddenColumns = ['buy_price', 'general_cat', 'specific_cat', 'brand'];

    // Estados de interfaz
    let activeEditingId = null;
    let isAddingInline = false;
    let isCriticalFilterActive = false;
    let searchQuery = "";

    // 2. Elementos del DOM
    const tableBody = document.getElementById("playground-table-body");
    const tableBox = document.getElementById("playground-table-box");
    const btnToggleFlat = document.getElementById("btn-toggle-flat");
    const viewToggleText = document.getElementById("view-toggle-text");
    const btnResetDemo = document.getElementById("btn-reset-demo");
    const btnCriticalFilter = document.getElementById("critical-filter-btn");
    const searchInput = document.getElementById("main-table-search");
    const btnAddRow = document.getElementById("add-row-btn");
    const btnExport = document.getElementById("btn-export");
    const btnImport = document.getElementById("btn-import");
    const btnManageColumns = document.getElementById("manage-columns-btn");

    // 3. Formateadores
    function formatCurrency(value) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', minimumFractionDigits: 2 }).format(value);
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    // 4. Renderizado de la Tabla (Réplica Dinámica y Fiel del Dashboard)
    function renderTable() {
        const visibleColumns = ALL_COLUMNS.filter(col => !hiddenColumns.includes(col.key));

        // Renderizar Cabecera de la Tabla
        const theadElement = document.getElementById("playground-table-head");
        if (theadElement) {
            const headerHTML = visibleColumns.map(col => {
                const iconHTML = col.icon ? ` <i class="${col.icon}"></i>` : '';
                const alignClass = col.align === 'center' ? 'class="col-center"' : (col.align === 'right' ? 'class="col-right"' : 'class="col-left"');
                return `<th ${alignClass}>${col.name}${iconHTML}</th>`;
            }).join('');
            theadElement.innerHTML = `<tr>${headerHTML}</tr>`;
        }

        // Filtrar datos
        let filtered = products;

        if (isCriticalFilterActive) {
            filtered = filtered.filter(p => p.stock <= p.min_stock);
        }

        if (searchQuery.trim().length > 0) {
            const query = searchQuery.toLowerCase().trim();
            filtered = filtered.filter(p => 
                p.name.toLowerCase().includes(query) ||
                (p.general_cat && p.general_cat.toLowerCase().includes(query)) ||
                (p.specific_cat && p.specific_cat.toLowerCase().includes(query)) ||
                (p.brand && p.brand.toLowerCase().includes(query)) ||
                (p.barcode && p.barcode.includes(query))
            );
        }

        let html = "";

        // Si se está agregando en línea, inyectar fila editable al principio
        if (isAddingInline) {
            let addRowHtml = '<tr class="editing-row">';
            visibleColumns.forEach(col => {
                const alignClass = col.align === 'center' ? 'class="col-center"' : (col.align === 'right' ? 'class="col-right"' : 'class="col-left"');
                if (col.key === 'id') {
                    addRowHtml += `<td ${alignClass} style="color:#aaa;">-</td>`;
                } else if (col.key === 'name') {
                    addRowHtml += `<td ${alignClass}><input type="text" id="inline-add-name" class="editing-input" placeholder="Mesa de Pc Minimalista..." required style="width:180px"></td>`;
                } else if (col.key === 'stock') {
                    addRowHtml += `<td ${alignClass}><input type="number" id="inline-add-stock" class="editing-input" value="10" required style="width:70px; text-align:center;"></td>`;
                } else if (col.key === 'min_stock') {
                    addRowHtml += `<td ${alignClass}><input type="number" id="inline-add-min-stock" class="editing-input" value="5" required style="width:70px; text-align:center;"></td>`;
                } else if (col.key === 'barcode') {
                    addRowHtml += `<td ${alignClass}><input type="text" id="inline-add-barcode" class="editing-input" placeholder="779..." style="width:110px"></td>`;
                } else if (col.key === 'sale_price') {
                    addRowHtml += `<td ${alignClass}><input type="number" id="inline-add-sale" class="editing-input" value="150000" step="0.01" required style="width:100px"></td>`;
                } else if (col.key === 'buy_price') {
                    addRowHtml += `<td ${alignClass}><input type="number" id="inline-add-buy" class="editing-input" value="95000" step="0.01" required style="width:100px"></td>`;
                } else if (col.key === 'general_cat') {
                    addRowHtml += `<td ${alignClass}><input type="text" id="inline-add-gen-cat" class="editing-input" value="Muebles" style="width:110px"></td>`;
                } else if (col.key === 'specific_cat') {
                    addRowHtml += `<td ${alignClass}><input type="text" id="inline-add-spec-cat" class="editing-input" value="Escritorios" style="width:110px"></td>`;
                } else if (col.key === 'brand') {
                    addRowHtml += `<td ${alignClass}><input type="text" id="inline-add-brand" class="editing-input" value="Minimalista" style="width:100px"></td>`;
                } else if (col.key === 'actions') {
                    addRowHtml += `
                        <td class="actions-cell ${alignClass}">
                            <div style="display:flex; gap:6px; justify-content:center;">
                                <button class="btn-inline-action save" onclick="saveInlineAdd()" title="Guardar Fila"><i class="ph ph-check" style="color: var(--accent-green, #a3be8c); font-weight:bold;"></i></button>
                                <button class="btn-inline-action cancel" onclick="cancelInlineAdd()" title="Cancelar"><i class="ph ph-x" style="color: var(--accent-red, #bf616a); font-weight:bold;"></i></button>
                            </div>
                        </td>`;
                }
            });
            addRowHtml += '</tr>';
            html += addRowHtml;
        }

        if (filtered.length === 0 && !isAddingInline) {
            const totalCols = visibleColumns.length;
            html += `
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; padding: 3rem; color: #888;">
                        <i class="ph ph-folder-open" style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        No se encontraron productos en la simulación.
                    </td>
                </tr>
            `;
        } else {
            filtered.forEach(p => {
                const isEditing = (activeEditingId === p.id);
                const isStockCritical = (p.stock <= p.min_stock);
                const stockCellClass = isStockCritical ? 'status-critical col-center' : 'col-center';

                if (isEditing) {
                    let editRowHtml = `<tr class="editing-row" id="row-${p.id}">`;
                    visibleColumns.forEach(col => {
                        const alignClass = col.align === 'center' ? 'class="col-center"' : (col.align === 'right' ? 'class="col-right"' : 'class="col-left"');
                        if (col.key === 'id') {
                            editRowHtml += `<td ${alignClass} style="color:#aaa;">${p.id}</td>`;
                        } else if (col.key === 'name') {
                            editRowHtml += `<td ${alignClass}><input type="text" id="inline-edit-name" class="editing-input" value="${escapeHTML(p.name)}" required style="width:180px"></td>`;
                        } else if (col.key === 'stock') {
                            editRowHtml += `<td ${alignClass}><input type="number" id="inline-edit-stock" class="editing-input" value="${p.stock}" required style="width:70px; text-align:center;"></td>`;
                        } else if (col.key === 'min_stock') {
                            editRowHtml += `<td ${alignClass}><input type="number" id="inline-edit-min-stock" class="editing-input" value="${p.min_stock}" required style="width:70px; text-align:center;"></td>`;
                        } else if (col.key === 'barcode') {
                            editRowHtml += `<td ${alignClass}><input type="text" id="inline-edit-barcode" class="editing-input" value="${p.barcode || ''}" style="width:110px"></td>`;
                        } else if (col.key === 'sale_price') {
                            editRowHtml += `<td ${alignClass}><input type="number" id="inline-edit-sale" class="editing-input" value="${p.sale_price}" step="0.01" required style="width:100px"></td>`;
                        } else if (col.key === 'buy_price') {
                            editRowHtml += `<td ${alignClass}><input type="number" id="inline-edit-buy" class="editing-input" value="${p.buy_price}" step="0.01" required style="width:100px"></td>`;
                        } else if (col.key === 'general_cat') {
                            editRowHtml += `<td ${alignClass}><input type="text" id="inline-edit-gen-cat" class="editing-input" value="${escapeHTML(p.general_cat)}" style="width:110px"></td>`;
                        } else if (col.key === 'specific_cat') {
                            editRowHtml += `<td ${alignClass}><input type="text" id="inline-edit-spec-cat" class="editing-input" value="${escapeHTML(p.specific_cat)}" style="width:110px"></td>`;
                        } else if (col.key === 'brand') {
                            editRowHtml += `<td ${alignClass}><input type="text" id="inline-edit-brand" class="editing-input" value="${escapeHTML(p.brand)}" style="width:100px"></td>`;
                        } else if (col.key === 'actions') {
                            editRowHtml += `
                                <td class="actions-cell ${alignClass}">
                                    <div style="display:flex; gap:6px; justify-content:center;">
                                        <button class="btn-inline-action save" onclick="saveInlineEdit(${p.id})" title="Guardar Cambios"><i class="ph ph-check" style="color: var(--accent-green, #a3be8c); font-weight:bold;"></i></button>
                                        <button class="btn-inline-action cancel" onclick="cancelInlineEdit()" title="Cancelar"><i class="ph ph-x" style="color: var(--accent-red, #bf616a); font-weight:bold;"></i></button>
                                    </div>
                                </td>`;
                        }
                    });
                    editRowHtml += '</tr>';
                    html += editRowHtml;
                } else {
                    let rowHtml = `<tr id="row-${p.id}">`;
                    visibleColumns.forEach(col => {
                        const alignClass = col.align === 'center' ? 'class="col-center"' : (col.align === 'right' ? 'class="col-right"' : 'class="col-left"');
                        if (col.key === 'id') {
                            rowHtml += `<td ${alignClass} style="color:#777;">${p.id}</td>`;
                        } else if (col.key === 'name') {
                            rowHtml += `<td ${alignClass} style="font-weight: 500;">${escapeHTML(p.name)}</td>`;
                        } else if (col.key === 'stock') {
                            rowHtml += `<td class="${stockCellClass}">${p.stock}</td>`;
                        } else if (col.key === 'min_stock') {
                            rowHtml += `<td ${alignClass} style="color:#666;">${p.min_stock}</td>`;
                        } else if (col.key === 'barcode') {
                            rowHtml += `<td ${alignClass} style="color:#555;">${p.barcode || '-'}</td>`;
                        } else if (col.key === 'sale_price') {
                            // Los precios se respetan con el color de texto por defecto (var(--color-black))
                            rowHtml += `<td ${alignClass} style="font-weight: bold; color: var(--color-black);">${formatCurrency(p.sale_price)}</td>`;
                        } else if (col.key === 'buy_price') {
                            rowHtml += `<td ${alignClass} style="color: #666;">${formatCurrency(p.buy_price)}</td>`;
                        } else if (col.key === 'general_cat') {
                            rowHtml += `<td ${alignClass}>${escapeHTML(p.general_cat)}</td>`;
                        } else if (col.key === 'specific_cat') {
                            rowHtml += `<td ${alignClass}>${escapeHTML(p.specific_cat)}</td>`;
                        } else if (col.key === 'brand') {
                            rowHtml += `<td ${alignClass}>${escapeHTML(p.brand)}</td>`;
                        } else if (col.key === 'actions') {
                            rowHtml += `
                                <td class="actions-cell ${alignClass}">
                                    <div style="display:flex; justify-content:center; gap:5px;">
                                        <button class="btn-icon action-edit" onclick="startInlineEdit(${p.id})" title="Editar Fila">
                                            <i class="ph ph-pencil-simple"></i>
                                        </button>
                                        <button class="btn-icon action-delete" onclick="deleteRow(${p.id})" title="Eliminar Fila">
                                            <i class="ph ph-trash" style="color: var(--accent-red);"></i>
                                        </button>
                                    </div>
                                </td>`;
                        }
                    });
                    rowHtml += '</tr>';
                    html += rowHtml;
                }
            });
        }

        tableBody.innerHTML = html;
    }

    // 5. Guía Interactiva Dinámica
    function updateGuideText(state) {
        const container = document.getElementById('guide-text');
        if (!container) return;
        
        container.classList.add('fade-out');
        
        setTimeout(() => {
            let text = "";
            switch (state) {
                case 'welcome':
                    text = "¡Te damos la bienvenida a la <strong>Sala de Pruebas</strong>! Aquí podés experimentar con nuestro gestor de stock en tiempo real.<br><br>Para comenzar, probá haciendo clic en el botón **'+ Añadir Fila'** en la esquina superior derecha del toolbar de la tabla.";
                    break;
                case 'adding':
                    text = "¡Excelente! Acabas de abrir una nueva fila de creación rápida directamente dentro de la tabla.<br><br>Ingresá los datos del nuevo producto directamente en las celdas y presioná el botón **Guardar (✔)** en la última columna para guardarlo localmente.";
                    break;
                case 'added':
                    text = "<strong>¡Fila inyectada con éxito!</strong> Como verás, la tabla calcula automáticamente si el Stock está por debajo del Stock Mínimo y, de ser así, resalta la celda en rojo.<br><br>Ahora, probá hacer clic en el **ícono de lápiz** de cualquier fila para editarla en línea.";
                    break;
                case 'editing':
                    text = "Ingresaste al modo de edición en línea de la fila.<br><br>Modificá cualquier valor (como reducir el stock por debajo del mínimo para ver la alerta de stock crítico) y presioná **Guardar (✔)**.";
                    break;
                case 'edited':
                    text = "<strong>¡Modificación exitosa!</strong> Los datos han sido actualizados en la simulación.<br><br>Por último, probá eliminar un producto haciendo clic en el **ícono de papelera** en la columna de Acciones.";
                    break;
                case 'deleted':
                    text = "<strong>¡Fila eliminada correctamente!</strong> El stock se depuró en tiempo real.<br><br>Podés seguir experimentando o usar el filtro de **Búsqueda** o el botón rojo de **Alertas Críticas** en la barra superior para ver cómo responde la tabla.";
                    break;
                case 'critical':
                    text = "<strong>Filtro de stock crítico</strong>: Acabas de filtrar los productos que están por debajo de su stock mínimo.<br><br>Esto ayuda al comerciante a saber qué productos necesita reponer de inmediato. Tocá el botón de alerta nuevamente para volver a ver todas las filas.";
                    break;
                case 'search':
                    text = "<strong>Búsqueda en tiempo real</strong>: La tabla se filtra instantáneamente a medida que escribís, buscando coincidencias en nombre, códigos, marcas o categorías.<br><br>¡Así de rápido responde nuestro motor de búsqueda inteligente!";
                    break;
                case 'export':
                    text = "<strong>Exportar datos</strong>: En la aplicación real, esta función descarga inmediatamente un archivo de Excel con todas las filas, columnas y filtros aplicados en el inventario activo.";
                    break;
                case 'import':
                    text = "<strong>Importar datos</strong>: Esta herramienta te permite subir un archivo CSV o Excel para cargar miles de productos en tu base de datos en pocos segundos, mapeando las columnas del archivo automáticamente.";
                    break;
            }
            container.innerHTML = text;
            container.classList.remove('fade-out');
        }, 200);
    }

    // 6. Asignadores de Eventos del Toolbar
    // Búsqueda en vivo
    searchInput.addEventListener("input", (e) => {
        searchQuery = e.target.value;
        renderTable();
        if (searchQuery.trim().length > 0) {
            updateGuideText('search');
        } else {
            updateGuideText('welcome');
        }
    });

    // Filtro crítico (advertencia)
    btnCriticalFilter.addEventListener("click", () => {
        isCriticalFilterActive = !isCriticalFilterActive;
        btnCriticalFilter.classList.toggle("active", isCriticalFilterActive);
        renderTable();
        if (isCriticalFilterActive) {
            updateGuideText('critical');
        } else {
            updateGuideText('welcome');
        }
    });

    // Añadir fila inline
    btnAddRow.addEventListener("click", () => {
        if (isAddingInline || activeEditingId !== null) return;
        isAddingInline = true;
        renderTable();
        updateGuideText('adding');
        
        // Enfocar el primer input inyectado si está visible
        const input = document.getElementById("inline-add-name");
        if (input) input.focus();
    });

    // Resetear demo
    btnResetDemo.addEventListener("click", () => {
        Swal.fire({
            title: '¿Reiniciar simulación?',
            text: 'Se restablecerán los datos a los valores iniciales de la captura.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, reiniciar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1b1b1b',
            cancelButtonColor: '#6c757d',
        }).then((result) => {
            if (result.isConfirmed) {
                products = [...defaultProducts];
                nextId = 11;
                activeEditingId = null;
                isAddingInline = false;
                isCriticalFilterActive = false;
                searchQuery = "";
                searchInput.value = "";
                btnCriticalFilter.classList.remove("active");
                hiddenColumns = ['buy_price', 'general_cat', 'specific_cat', 'brand'];
                
                renderTable();
                updateGuideText('welcome');
                
                Swal.fire({
                    title: '¡Reiniciado!',
                    text: 'Los datos se han restaurado con éxito.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    });

    // Exportar (Simulado)
    btnExport.addEventListener("click", () => {
        updateGuideText('export');
        Swal.fire({
            title: 'Exportar Inventario (Simulación)',
            text: 'En el panel real, se generará y descargará un archivo de Excel (.xlsx) con los filtros actuales.',
            icon: 'info',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#1b1b1b'
        });
    });

    // Importar (Simulado)
    btnImport.addEventListener("click", () => {
        updateGuideText('import');
        Swal.fire({
            title: 'Importación de Datos (Simulación)',
            text: 'Esta función te permite subir un CSV o Excel y mapear columnas del archivo para una carga masiva en segundos.',
            icon: 'info',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#1b1b1b'
        });
    });

    // Alternar perspectiva 3D
    btnToggleFlat.addEventListener("click", () => {
        tableBox.classList.toggle("flat-view");
        if (tableBox.classList.contains("flat-view")) {
            viewToggleText.textContent = "Inclinación 3D";
            btnToggleFlat.querySelector("i").className = "ph-bold ph-cube";
        } else {
            viewToggleText.textContent = "Fijar Vista 2D";
            btnToggleFlat.querySelector("i").className = "ph-bold ph-perspective";
        }
    });

    // Ojo: abrir gestor de columnas
    if (btnManageColumns) {
        btnManageColumns.addEventListener("click", () => {
            window.openColumnManager();
        });
    }

    // 7. Funciones del Gestor de Columnas expuestas globalmente
    window.openColumnManager = function() {
        const modal = document.getElementById("column-manager-modal");
        const listContainer = document.getElementById("column-manager-list");
        if (!modal || !listContainer) return;

        listContainer.innerHTML = "";
        ALL_COLUMNS.forEach(col => {
            // No ocultar 'id' o 'actions' en la maqueta
            if (col.key === 'id' || col.key === 'actions') return;

            const isChecked = !hiddenColumns.includes(col.key);
            
            const item = document.createElement("div");
            item.className = "sortable-item";

            item.innerHTML = `
                <div style="display:flex; align-items:center; gap: 10px;">
                    <i class="ph ph-eye" style="color: #9ca3af; font-size: 1.2rem;"></i>
                    <span class="col-name">${col.name}</span>
                </div>
                <input type="checkbox" value="${col.key}" ${isChecked ? 'checked' : ''} 
                       style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--accent-color);">
            `;
            listContainer.appendChild(item);
        });

        modal.classList.remove("hidden");
        document.body.style.overflow = "hidden";
    };

    window.closeColumnManager = function() {
        const modal = document.getElementById("column-manager-modal");
        if (modal) {
            modal.classList.add("hidden");
            document.body.style.overflow = "";
        }
    };

    window.saveColumnPreferences = function() {
        const listContainer = document.getElementById("column-manager-list");
        if (!listContainer) return;

        const checkboxes = listContainer.querySelectorAll("input[type='checkbox']");
        const newHidden = [];
        
        ALL_COLUMNS.forEach(col => {
            if (col.key === 'id' || col.key === 'actions') return;
            
            let found = false;
            checkboxes.forEach(cb => {
                if (cb.value === col.key) {
                    found = true;
                    if (!cb.checked) {
                        newHidden.push(col.key);
                    }
                }
            });
            if (!found && hiddenColumns.includes(col.key)) {
                newHidden.push(col.key);
            }
        });

        hiddenColumns = newHidden;
        renderTable();
        closeColumnManager();

        Swal.fire({
            title: '¡Guardado!',
            text: 'Columnas actualizadas con éxito.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    };

    // 8. Funciones CRUD inline expuestas globalmente
    window.saveInlineAdd = function() {
        const nameInput = document.getElementById("inline-add-name");
        const stockInput = document.getElementById("inline-add-stock");
        const minStockInput = document.getElementById("inline-add-min-stock");
        const barcodeInput = document.getElementById("inline-add-barcode");
        const saleInput = document.getElementById("inline-add-sale");
        const buyInput = document.getElementById("inline-add-buy");
        const genInput = document.getElementById("inline-add-gen-cat");
        const specInput = document.getElementById("inline-add-spec-cat");
        const brandInput = document.getElementById("inline-add-brand");

        const name = nameInput ? nameInput.value.trim() : "Mesa de Pc Minimalista";
        const stock = stockInput ? parseInt(stockInput.value) : 10;
        const min_stock = minStockInput ? parseInt(minStockInput.value) : 5;
        const barcode = barcodeInput ? barcodeInput.value.trim() : "";
        const sale_price = saleInput ? parseFloat(saleInput.value) : 150000;
        const buy_price = buyInput ? parseFloat(buyInput.value) : 95000;
        const general_cat = genInput ? genInput.value.trim() : "Muebles";
        const specific_cat = specInput ? specInput.value.trim() : "Escritorios";
        const brand = brandInput ? brandInput.value.trim() : "Minimalista";

        if (!name) {
            Swal.fire({ title: 'Error', text: 'El nombre es obligatorio.', icon: 'error', confirmButtonColor: '#1b1b1b' });
            return;
        }

        products.unshift({
            id: nextId++,
            name,
            stock: isNaN(stock) ? 0 : stock,
            min_stock: isNaN(min_stock) ? 0 : min_stock,
            barcode,
            sale_price: isNaN(sale_price) ? 0 : sale_price,
            buy_price: isNaN(buy_price) ? 0 : buy_price,
            general_cat,
            specific_cat,
            brand
        });

        isAddingInline = false;
        renderTable();
        updateGuideText('added');

        Swal.fire({
            title: '¡Fila Creada!',
            text: 'El producto se incorporó correctamente en la maqueta.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    };

    window.cancelInlineAdd = function() {
        isAddingInline = false;
        renderTable();
        updateGuideText('welcome');
    };

    window.startInlineEdit = function(id) {
        if (isAddingInline) return;
        activeEditingId = id;
        renderTable();
        updateGuideText('editing');
        
        // Enfocar el primer input de la edición si está visible
        const input = document.getElementById("inline-edit-name");
        if (input) input.focus();
    };

    window.saveInlineEdit = function(id) {
        const nameInput = document.getElementById("inline-edit-name");
        const stockInput = document.getElementById("inline-edit-stock");
        const minStockInput = document.getElementById("inline-edit-min-stock");
        const barcodeInput = document.getElementById("inline-edit-barcode");
        const saleInput = document.getElementById("inline-edit-sale");
        const buyInput = document.getElementById("inline-edit-buy");
        const genInput = document.getElementById("inline-edit-gen-cat");
        const specInput = document.getElementById("inline-edit-spec-cat");
        const brandInput = document.getElementById("inline-edit-brand");

        const original = products.find(p => p.id === id) || {};

        const name = nameInput ? nameInput.value.trim() : (original.name || "");
        const stock = stockInput ? parseInt(stockInput.value) : (original.stock || 0);
        const min_stock = minStockInput ? parseInt(minStockInput.value) : (original.min_stock || 0);
        const barcode = barcodeInput ? barcodeInput.value.trim() : (original.barcode || "");
        const sale_price = saleInput ? parseFloat(saleInput.value) : (original.sale_price || 0);
        const buy_price = buyInput ? parseFloat(buyInput.value) : (original.buy_price || 0);
        const general_cat = genInput ? genInput.value.trim() : (original.general_cat || "");
        const specific_cat = specInput ? specInput.value.trim() : (original.specific_cat || "");
        const brand = brandInput ? brandInput.value.trim() : (original.brand || "");

        if (!name) {
            Swal.fire({ title: 'Error', text: 'El nombre es obligatorio.', icon: 'error', confirmButtonColor: '#1b1b1b' });
            return;
        }

        const index = products.findIndex(p => p.id === id);
        if (index !== -1) {
            products[index] = {
                id,
                name,
                stock: isNaN(stock) ? 0 : stock,
                min_stock: isNaN(min_stock) ? 0 : min_stock,
                barcode,
                sale_price: isNaN(sale_price) ? 0 : sale_price,
                buy_price: isNaN(buy_price) ? 0 : buy_price,
                general_cat,
                specific_cat,
                brand
            };
        }

        activeEditingId = null;
        renderTable();
        updateGuideText('edited');

        Swal.fire({
            title: '¡Guardado!',
            text: 'Fila modificada con éxito.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    };

    window.cancelInlineEdit = function() {
        activeEditingId = null;
        renderTable();
        updateGuideText('added');
    };

    window.deleteRow = function(id) {
        if (isAddingInline || activeEditingId !== null) return;
        
        Swal.fire({
            title: '¿Eliminar fila de maqueta?',
            text: 'Esta acción simula la baja física del stock.',
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
        }).then((result) => {
            if (result.isConfirmed) {
                products = products.filter(p => p.id !== id);
                renderTable();
                updateGuideText('deleted');
                Swal.fire({
                    title: '¡Eliminado!',
                    text: 'La fila se borró de la simulación.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    };

    // Inicializar maqueta
    renderTable();
    updateGuideText('welcome');

    // Add keyboard shortcuts for row editing
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            if (e.target.matches('.editing-input')) {
                const tr = e.target.closest('tr');
                if (tr) {
                    const saveBtn = tr.querySelector('.btn-inline-action.save');
                    if (saveBtn) {
                        e.preventDefault();
                        saveBtn.click();
                    }
                }
            }
        } else if (e.key === 'Escape') {
            if (e.target.matches('.editing-input')) {
                const tr = e.target.closest('tr');
                if (tr) {
                    const cancelBtn = tr.querySelector('.btn-inline-action.cancel');
                    if (cancelBtn) {
                        e.preventDefault();
                        cancelBtn.click();
                    }
                }
            }
        }
    });
});
