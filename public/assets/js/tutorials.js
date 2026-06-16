/* public/assets/js/tutorials.js */

const TUTORIALS_DATA = [
    {
        id: "inventarios",
        title: "Inventarios y Columnas",
        icon: "ph-fill ph-database",
        description: "Aprende a crear bases de datos y configurar columnas esenciales.",
        topics: [
            {
                id: "crear-inventario",
                title: "Crear y Cambiar Inventarios",
                description: "Cómo crear nuevos inventarios y navegar entre ellos.",
                noVideo: true,
                content: `
                    <p>En StockiFy puedes organizar tus productos en múltiples bases de datos llamadas <strong>Inventarios</strong>. Esto es útil para separar sucursales, depósitos o tipos de negocio totalmente distintos.</p>
                    
                    <h3>Cómo cambiar de inventario:</h3>
                    <ul>
                        <li>Haz clic en <strong>"Cambiar Inventario"</strong> en la barra lateral del panel de control.</li>
                        <li>Verás el listado de tus bases de datos activas. Haz clic en <strong>"Seleccionar"</strong> en el inventario al que deseas ingresar.</li>
                    </ul>

                    <h3>Crear un nuevo inventario:</h3>
                    <ul>
                        <li>Haz clic en <strong>"Crear Nuevo Inventario"</strong> en la barra lateral.</li>
                        <li>Escribe un nombre identificativo para tu inventario.</li>
                        <li>Puedes empezar desde cero o usar una plantilla base.</li>
                    </ul>

                    <div class="tutorial-alert info">
                        <i class="ph-bold ph-info"></i>
                        <div class="tutorial-alert-content">
                            <strong>Límites según tu Plan:</strong>
                            El Plan Básico permite tener un único inventario activo. Si necesitas múltiples inventarios para sucursales o depósitos, considera subir al Plan Profesional.
                        </div>
                    </div>
                `
            },
            {
                id: "configurar-tabla",
                title: "Configurar Tabla y Columnas",
                description: "Mapea tus columnas para habilitar el sistema de caja y añadir campos manuales.",
                content: `
                    <p>La flexibilidad de StockiFy radica en que puedes configurar tu tabla para que tenga las columnas que tu negocio necesite (Ubicación, Talle, Marca, etc.), además de las columnas automáticas básicas.</p>
                    
                    <h3>Identificación de Columnas (Mapeo de Referencias):</h3>
                    <p>Para que el sistema de caja, transacciones y analíticas funcione, StockiFy necesita saber a qué columnas corresponden los datos clave. Ve a <strong>"Configurar Tabla"</strong> &rarr; <strong>"Identificación de Columnas"</strong> e indica qué columna de tu tabla representa:</p>
                    <ol>
                        <li><strong>Nombre / Producto:</strong> Nombre identificativo del artículo.</li>
                        <li><strong>Stock Actual:</strong> Cantidad física disponible.</li>
                        <li><strong>Precio Compra (Costo):</strong> Cuánto te costó adquirir el artículo.</li>
                        <li><strong>Precio Venta:</strong> Precio al que vendes al público.</li>
                    </ol>

                    <h3>Añadir Columnas Manuales:</h3>
                    <ul>
                        <li>En <strong>"Configurar Tabla"</strong>, ve a la sección <strong>"Gestionar Columnas Manuales"</strong>.</li>
                        <li>Escribe el nombre de la columna que deseas añadir (ej. "Código de Barra") y presiona <strong>"Añadir"</strong>.</li>
                        <li>Aparecerá inmediatamente como una nueva columna editable en tu vista principal.</li>
                    </ul>

                    <div class="tutorial-alert warning">
                        <i class="ph-bold ph-warning-circle"></i>
                        <div class="tutorial-alert-content">
                            <strong>¡Mapeo Obligatorio!</strong>
                            Si no realizas la "Identificación de Columnas", no podrás registrar ventas ni compras en la sección de transacciones, ya que el sistema no sabrá de qué columna descontar stock o qué precios calcular.
                        </div>
                    </div>
                `
            },
            {
                id: "stock-minimo",
                title: "Control de Stock Mínimo",
                description: "Configura alertas visuales para saber cuándo reponer stock.",
                content: `
                    <p>El control de stock mínimo te ayuda a evitar quiebres de inventario avisándote visualmente cuando un producto está por agotarse.</p>
                    
                    <h3>Cómo activarlo:</h3>
                    <ol>
                        <li>Ve a <strong>"Configurar Tabla"</strong> en la barra lateral.</li>
                        <li>Abre la sección de <strong>"Funcionalidades Extra"</strong>.</li>
                        <li>Marca la casilla de <strong>"Control de Stock Mínimo"</strong> y presiona <strong>"Aplicar Cambios"</strong>.</li>
                    </ol>

                    <h3>¿Cómo funciona?</h3>
                    <p>Al activarlo, se creará automáticamente una columna llamada <strong>"Stock Mínimo"</strong> en tu tabla principal. Podrás asignarle un valor a cada producto.</p>
                    <ul>
                        <li>Si el stock real de un producto es <strong>menor o igual</strong> al número definido en "Stock Mínimo", la fila del producto se resaltará con un aviso en color amarillo/rojo en tu panel.</li>
                        <li>Habilitará un botón de filtro rápido de <strong>"Stock Crítico"</strong> (icono de advertencia) en la cabecera de la tabla para ver solo los productos que requieren reposición.</li>
                    </ul>
                `
            },
            {
                id: "filas-gestion",
                title: "Crear y Editar Filas (Productos)",
                description: "Cómo ingresar nuevos productos y modificar su información en la tabla.",
                content: `
                    <p>Las filas representan tus productos o servicios en inventario. StockiFy te permite crearlas y editarlas con total libertad, como si fuera una hoja de cálculo.</p>
                    
                    <h3>Cómo añadir un nuevo producto:</h3>
                    <ol>
                        <li>Ve a la vista principal <strong>"Ver Datos"</strong>.</li>
                        <li>Haz clic en el botón <strong>"+ Añadir Fila"</strong> en la esquina superior derecha de la tabla.</li>
                        <li>Se creará una fila en blanco al inicio de la tabla lista para rellenar.</li>
                    </ol>

                    <h3>Cómo editar información:</h3>
                    <ul>
                        <li>Haz doble clic sobre la celda que deseas modificar. Se abrirá un cuadro de texto o número.</li>
                        <li>Escribe el nuevo valor y presiona <strong>Enter</strong> o haz clic fuera de la celda para guardar el cambio de forma instantánea.</li>
                    </ul>
                `
            },
            {
                id: "columnas-gestion",
                title: "Gestionar Columnas y Colores",
                description: "Añade nuevos campos, renombra columnas o configúralas en la vista.",
                content: `
                    <p>Puedes personalizar la estructura de tu inventario agregando o renombrando campos y cambiando los colores para clasificar mejor la información.</p>
                    
                    <h3>Crear y eliminar columnas:</h3>
                    <ul>
                        <li>Ve a <strong>"Configurar Tabla"</strong> &rarr; <strong>"Gestionar Columnas Manuales"</strong>.</li>
                        <li>Escribe el nombre de la nueva columna (ej. "Talle") y presiona <strong>"Añadir"</strong>.</li>
                        <li>Para borrar una columna, búscala en el listado inferior de la misma pestaña y haz clic en el icono de la papelera (<strong>"Eliminar"</strong>).</li>
                    </ul>

                    <h3>Mostrar/Ocultar y cambiar colores de columnas:</h3>
                    <ul>
                        <li>Haz clic en el icono del ojo (<strong>"Gestionar Columnas"</strong>) en la cabecera de la tabla principal.</li>
                        <li>Verás el listado de tus columnas. Marca o desmarca las casillas para ocultar campos que no necesites ver a diario.</li>
                        <li>Para cambiar el color de una columna, selecciona una etiqueta de color dentro de las opciones de visualización para clasificar de manera visual tus datos.</li>
                    </ul>
                `
            },
            {
                id: "importar-columna",
                title: "Importar datos para una sola Columna",
                description: "Actualiza masivamente los datos de un campo específico.",
                content: `
                    <p>Si ya posees una columna estructurada en un archivo externo (como códigos de barra o stock mínimo) y quieres importarla a una columna que ya creaste en StockiFy:</p>
                    
                    <h3>Paso a paso:</h3>
                    <ol>
                        <li>Asegúrate de haber creado primero la columna manual de destino en <strong>"Configurar Tabla"</strong>.</li>
                        <li>Haz clic en <strong>"Importar Datos"</strong> e inicia el asistente de importación con tu archivo CSV.</li>
                        <li>En el panel de mapeo de importación, asocia la columna origen de tu archivo CSV únicamente con la columna correspondiente en StockiFy, dejando las demás vacías si no quieres sobreescribir.</li>
                        <li>Presiona <strong>"Procesar"</strong> para completar la importación de esa columna en particular.</li>
                    </ol>
                `
            },
            {
                id: "eliminar-datos",
                title: "Eliminar Filas y Columnas",
                description: "Cómo borrar productos y columnas del sistema.",
                content: `
                    <p>Si deseas dar de baja productos o campos que ya no utilizas en tu negocio, puedes borrarlos permanentemente del sistema.</p>
                    
                    <h3>Eliminar un producto (Fila):</h3>
                    <ol>
                        <li>En la tabla principal, selecciona la fila o celdas del producto que deseas borrar.</li>
                        <li>Haz clic en el botón de eliminación en la parte derecha de la fila (o usa los controles rápidos).</li>
                        <li>Confirma la acción en el cuadro de diálogo emergente de confirmación.</li>
                    </ol>

                    <h3>Eliminar un campo (Columna):</h3>
                    <ul>
                        <li>Ve a <strong>"Configurar Tabla"</strong> &rarr; <strong>"Gestionar Columnas Manuales"</strong>.</li>
                        <li>En el listado de columnas creadas por ti, busca la que deseas eliminar.</li>
                        <li>Haz clic en el icono de papelera. Recuerda que esto borrará los datos de esa columna para todos los productos de este inventario.</li>
                    </ul>
                `
            },
            {
                id: "organizar-datos",
                title: "Organizar datos de Menor a Mayor",
                description: "Ordena los datos de tu tabla haciendo clic en el encabezado.",
                content: `
                    <p>StockiFy te permite ordenar tus productos de forma ascendente o descendente basándose en cualquier columna.</p>
                    
                    <h3>Cómo ordenar:</h3>
                    <ul>
                        <li>Haz clic en el **nombre de la columna** en el encabezado de la tabla principal (ej. haz clic en "Stock" o "Nombre").</li>
                        <li>Al hacer clic una vez, la tabla se ordenará de <strong>Menor a Mayor</strong> (o de la A a la Z).</li>
                        <li>Al hacer clic nuevamente, se ordenará de <strong>Mayor a Menor</strong> (o de la Z a la A).</li>
                        <li>Aparecerá un pequeño icono de flecha junto al nombre de la columna indicando el orden actual.</li>
                    </ul>
                `
            }
        ]
    },
    {
        id: "transacciones",
        title: "Transacciones y Caja",
        icon: "ph-fill ph-currency-dollar",
        description: "Aprende a registrar ventas, compras y a manejar el balance diario.",
        topics: [
            {
                id: "registrar-ingreso",
                title: "Registrar Ingresos (Ventas)",
                description: "Detalle de los tres sub-paneles y cómo facturar ventas paso a paso.",
                content: `
                    <p>Al hacer clic en <strong>"Registrar Ingreso"</strong> en la barra lateral del panel de control, accederás a la pantalla principal de Ventas. Allí verás el historial de transacciones previas y resúmenes diarios de caja.</p>
                    
                    <p>Para facturar una nueva transacción, presiona el botón <strong>"+ Nueva Venta"</strong> (o "+ Registrar Ingreso"). Se abrirá una interfaz dividida en tres sub-ventanas:</p>

                    <h3>1. Sub-ventana de Catálogo (Izquierda):</h3>
                    <ul>
                        <li>Muestra todos tus productos activos y su cantidad en stock real. Haz clic en ellos para sumarlos al carrito.</li>
                        <li><strong>Botón "Manual":</strong> Si vendes un servicio, mano de obra, o un artículo no stockeado, presiona el botón <strong>"Manual"</strong> al lado de la barra de búsqueda para crear un ítem libre asignándole nombre y precio al instante.</li>
                    </ul>

                    <h3>2. Sub-ventana de Artículos Seleccionados (Centro):</h3>
                    <ul>
                        <li>Aquí se lista todo lo que vas sumando a la venta.</li>
                        <li>Puedes aumentar o disminuir las unidades de cada ítem de forma directa.</li>
                        <li><strong>Cambio de Precio Unitario:</strong> Si necesitas aplicar una tarifa especial, haz <strong>doble clic</strong> sobre el precio unitario del artículo para editarlo libremente en esa venta en particular.</li>
                    </ul>

                    <h3>3. Sub-ventana de Información y Pago (Derecha):</h3>
                    <ul>
                        <li><strong>Notas:</strong> Agrega comentarios u observaciones de la transacción.</li>
                        <li><strong>Cliente / Vendedor:</strong> Vincula la venta a un cliente de tu CRM o asigna al empleado a cargo (para el cálculo de su comisión).</li>
                        <li><strong>Descuentos:</strong> Aplica un descuento manual de forma porcentual (%) o fija ($).</li>
                        <li><strong>Información de Pago:</strong> Funciona como una calculadora que te ayuda a registrar con qué método (Efectivo, Débito, etc.) y moneda paga el cliente, calculando el vuelto exacto y actualizando las métricas de caja.</li>
                    </ul>
                `
            },
            {
                id: "registrar-egreso",
                title: "Registrar Egresos (Compras)",
                description: "Cómo registrar reposiciones de stock y compras a proveedores.",
                content: `
                    <p>Al hacer clic en <strong>"Registrar Egreso"</strong>, verás el historial de compras. Para registrar una nueva compra de mercadería, haz clic en <strong>"+ Registrar Compra"</strong>. La interfaz se dividirá en paneles similares a la de ventas:</p>
                    
                    <h3>Flujo de Compra:</h3>
                    <ul>
                        <li><strong>Productos (Catálogo):</strong> Elige del listado el producto que estás reponiendo o añade conceptos no stockeables de forma manual.</li>
                        <li><strong>Costo Unitario y Cantidad:</strong> Ingresa el costo pactado de compra y las unidades adquiridas.</li>
                        <li><strong>Proveedor:</strong> Vincula la transacción al proveedor de tu CRM que te despacha el producto.</li>
                        <li><strong>Cierre de Transacción:</strong> Selecciona el método de pago utilizado. Al guardar, el stock del inventario principal se incrementará de manera automática y se descontará el dinero de tu caja.</li>
                    </ul>
                `
            },
            {
                id: "reporte-diario",
                title: "Reportes Diarios Automáticos",
                description: "Configura balances automáticos en WhatsApp y Correo.",
                noVideo: true,
                content: `
                    <p>StockiFy cuenta con reportes automáticos para que no tengas que preocuparte por generar estadísticas al final de tu jornada laboral.</p>
                    
                    <h3>Cómo funciona:</h3>
                    <ul>
                        <li>Ve a <strong>"Configurar Tabla"</strong> &rarr; <strong>"Funcionalidades Extra"</strong> y activa <strong>"Reporte Diario Automático"</strong>.</li>
                        <li>Todos los días a las <strong>22:00 hs</strong>, el sistema calculará las ventas, egresos y balance del día.</li>
                        <li>Recibirás este resumen de forma automática tanto en tu **correo electrónico** como en tu **WhatsApp** (si tienes el canal configurado).</li>
                    </ul>
                `
            },
            {
                id: "reporte-critico",
                title: "Reporte de Stock Crítico",
                description: "Envía alertas manuales de productos con bajo stock por Email.",
                content: `
                    <p>Además de los reportes diarios automáticos, puedes generar un reporte manual tipo lista para saber qué mercadería comprar.</p>
                    
                    <h3>Cómo generar el reporte:</h3>
                    <ol>
                        <li>En la vista de tu tabla de datos, presiona el botón <strong>"Stock Crítico"</strong> (icono de advertencia) para filtrar y ver sólo los productos con bajo stock.</li>
                        <li>Aparecerá el botón <strong>"Enviar Reporte"</strong> (icono de avión de papel).</li>
                        <li>Haz clic en él. El sistema generará una lista detallada con todos los productos faltantes y sus unidades críticas y la enviará directamente a tu **correo electrónico** en formato PDF o de texto listo para usar.</li>
                    </ol>
                `
            },
            {
                id: "enviar-tickets",
                title: "Enviar Tickets y Comprobantes",
                description: "Cómo enviar comprobantes de venta a tus clientes.",
                content: `
                    <p>Una vez registrada una venta, puedes enviar de manera digital un comprobante de pago o ticket a tus clientes.</p>
                    
                    <h3>Requisitos Importantes:</h3>
                    <div class="tutorial-alert warning">
                        <i class="ph-bold ph-warning-circle"></i>
                        <div class="tutorial-alert-content">
                            <strong>¿Por qué no me permite enviar el ticket?</strong>
                            El sistema no te permitirá enviar el comprobante por correo si se cumple alguna de estas condiciones:
                            <ul>
                                <li>No vinculaste a ningún cliente a la venta (quedó como 'No asignado').</li>
                                <li>El cliente asignado no tiene una dirección de correo electrónico válida configurada en su perfil.</li>
                            </ul>
                        </div>
                    </div>

                    <h3>Cómo solucionarlo:</h3>
                    <p>Asegúrate de buscar al cliente en tu lista CRM e ingresar su correo antes de procesar la venta. Si ya realizaste la venta, puedes ir a <strong>"Historial"</strong>, buscar la transacción, editar el cliente y enviar el comprobante.</p>
                `
            },
            {
                id: "error-columnas-mapeo",
                title: "Errores al Guardar: Mapeo Obligatorio",
                description: "Qué hacer si el sistema no te permite guardar transacciones.",
                content: `
                    <p>Si al registrar una venta o compra el sistema muestra un mensaje de error o no te permite presionar el botón de confirmar, lo más probable es que falten las referencias de la tabla.</p>
                    
                    <h3>¿Por qué sucede esto?</h3>
                    <p>Para poder descontar stock o calcular ganancias, StockiFy debe saber qué columna es cada cosa. Si añadiste productos pero el sistema no sabe cuál de tus columnas indica el "Stock" o el "Precio de Venta", la transacción fallará.</p>

                    <h3>Cómo solucionarlo:</h3>
                    <ol>
                        <li>Ve a <strong>"Configurar Tabla"</strong> en el menú lateral.</li>
                        <li>Abre la sección <strong>"Identificación de Columnas"</strong>.</li>
                        <li>Verifica que los campos de Nombre, Stock, Precio de Compra y Precio de Venta tengan asignadas las columnas correctas de tu tabla.</li>
                        <li>Guarda los cambios e intenta registrar la transacción nuevamente.</li>
                    </ol>
                `
            }
        ]
    },
    {
        id: "crm",
        title: "CRM y Colaboradores",
        icon: "ph-fill ph-users-three",
        description: "Gestiona permisos de empleados, clientes y proveedores.",
        topics: [
            {
                id: "clientes-proveedores",
                title: "Clientes y Proveedores",
                description: "Cómo registrar contactos y hacer seguimiento de sus datos de facturación.",
                content: `
                    <p>La gestión de contactos de StockiFy te ayuda a llevar un registro formal de las personas y empresas con las que haces negocios.</p>
                    
                    <h3>Módulo de Clientes:</h3>
                    <ul>
                        <li>Accede a <strong>"Clientes"</strong> en el menú lateral.</li>
                        <li>Puedes añadir clientes indicando su nombre completo, email, teléfono, dirección y DNI/CUIT.</li>
                        <li>Al registrar un ingreso, podrás asociar este cliente para tener un registro de quién compró cada artículo.</li>
                    </ul>

                    <h3>Módulo de Proveedores:</h3>
                    <ul>
                        <li>Accede a <strong>"Proveedores"</strong> en el menú lateral.</li>
                        <li>Registra las empresas que te distribuyen mercadería.</li>
                        <li>Al registrar un egreso (compra), selecciona el proveedor correspondiente para llevar un seguimiento preciso de tus gastos de abastecimiento.</li>
                    </ul>
                `
            },
            {
                id: "colaboradores",
                title: "Colaboradores y Permisos Dinámicos (RBAC)",
                description: "Configura los permisos dinámicos de tu equipo de trabajo.",
                content: `
                    <p>Si trabajas con un equipo, puedes invitarlos a tu inventario configurando qué secciones del panel pueden visualizar o modificar.</p>
                    
                    <h3>Roles Dinámicos:</h3>
                    <div class="tutorial-alert info">
                        <i class="ph-bold ph-shield-check"></i>
                        <div class="tutorial-alert-content">
                            <strong>Permisos 100% Personalizados:</strong>
                            A diferencia de otros sistemas, en StockiFy los permisos no son estáticos ni están predefinidos. El Propietario (Owner/Director) puede configurar dinámicamente y de forma individual qué permisos tiene cada rol (Administrador o Empleado).
                        </div>
                    </div>

                    <h3>Cómo configurar permisos:</h3>
                    <ul>
                        <li>Ve a la sección de colaboradores en tu panel de control.</li>
                        <li>Puedes activar o desactivar casillas específicas (ej. "Permitir ver analíticas de ganancia", "Permitir editar columnas de configuración") para el rol del colaborador.</li>
                        <li>Al guardar, el menú lateral del empleado se actualizará instantáneamente ocultando o mostrando las secciones configuradas.</li>
                    </ul>
                `
            },
            {
                id: "empleados-categorias",
                title: "Gestión de Empleados y Categorías",
                description: "Cómo registrar a tus empleados y configurar comisiones por categorías.",
                content: `
                    <p>Llevar un registro de tus empleados te permite auditar quién realiza cada operación y liquidar comisiones de forma automatizada.</p>
                    
                    <h3>Registrar Empleados:</h3>
                    <ul>
                        <li>Accede a <strong>"Empleados"</strong> en la barra lateral del panel de control.</li>
                        <li>Presiona <strong>"Añadir Empleado"</strong> para registrar sus datos personales e identificarlo en el sistema.</li>
                    </ul>

                    <h3>El mundo de las Categorías y Comisiones:</h3>
                    <p>Al clasificar tus productos por categorías (ej: "Electrónica", "Indumentaria"), puedes definir esquemas de comisiones variables:</p>
                    <ul>
                        <li>Configura qué porcentaje de comisión se lleva el vendedor sobre la venta según la <strong>Categoría</strong> del producto vendido.</li>
                        <li>El sistema calculará automáticamente a fin de mes la comisión acumulada para cada empleado basándose en las ventas registradas bajo su nombre.</li>
                    </ul>
                `
            }
        ]
    },
    {
        id: "analiticas",
        title: "Analíticas e Importación",
        icon: "ph-fill ph-chart-line",
        description: "Estudia el rendimiento de tu negocio y carga datos masivos vía Excel.",
        topics: [
            {
                id: "ver-analiticas",
                title: "Panel de Analíticas y Estadísticas",
                description: "Conoce todas las métricas de rendimiento disponibles en StockiFy.",
                content: `
                    <p>El panel de analíticas procesa todas las transacciones para ofrecerte una radiografía financiera completa de tu negocio.</p>
                    
                    <h3>Métricas Financieras Disponibles:</h3>
                    <ol>
                        <li><strong>Ingresos Totales:</strong> Suma bruta de todas las ventas cobradas.</li>
                        <li><strong>Egresos / Inversión:</strong> Dinero total invertido en reponer mercadería o gastos comerciales.</li>
                        <li><strong>Margen de Ganancia Neto:</strong> El beneficio real neto del negocio (Ingresos menos Egresos).</li>
                        <li><strong>Ticket Promedio:</strong> Gasto medio de tus clientes por cada compra.</li>
                        <li><strong>Total de Transacciones:</strong> Cantidad de facturas emitidas y compras registradas.</li>
                        <li><strong>Top Productos Más Vendidos:</strong> Gráfico que identifica cuáles son tus artículos de mayor rotación.</li>
                        <li><strong>Comisiones de Vendedores:</strong> Liquidaciones pendientes y comisiones ganadas por tu personal.</li>
                        <li><strong>Mejores Clientes:</strong> Identifica a tus compradores recurrentes y de mayor volumen.</li>
                    </ol>

                    <p>Puedes aplicar filtros de fechas desde la barra superior de la sección de analíticas para consultar datos de hoy, ayer, el mes en curso o un rango histórico personalizado.</p>
                `
            },
            {
                id: "importar-datos",
                title: "Importar Datos desde Excel (CSV)",
                description: "Carga masiva de productos desde archivos CSV delimitados por comas.",
                content: `
                    <p>Si posees tu listado de productos en Excel, puedes cargarlo masivamente a StockiFy ahorrando horas de carga manual.</p>
                    
                    <h3>Instrucciones de Importación:</h3>
                    <ol>
                        <li>Abre tu planilla de cálculo y guárdala con el formato <strong>CSV (delimitado por comas)</strong>.</li>
                        <li>Haz clic en el botón <strong>"Importar Datos"</strong> en la cabecera del panel.</li>
                        <li>Arrastra el archivo CSV y selecciona <strong>"Siguiente"</strong>.</li>
                        <li><strong>Mapeo de Columnas:</strong> Vincula los títulos de las columnas de tu CSV con las columnas de tu inventario (ej. asocia tu columna "Precio Venta" con la de StockiFy).</li>
                        <li>Confirma la importación. Los productos aparecerán cargados inmediatamente.</li>
                    </ol>
                `
            },
            {
                id: "exportar-datos",
                title: "Exportar Datos (Excel)",
                description: "Descarga copias de seguridad de tus tablas en formato XLSX.",
                content: `
                    <p>Mantén copias de seguridad locales de toda la información de tus productos e inventarios.</p>
                    
                    <h3>Cómo Exportar:</h3>
                    <ul>
                        <li>En la vista de tu inventario principal, haz clic en el botón <strong>"Exportar"</strong> en la cabecera.</li>
                        <li>El sistema procesará tu tabla en tiempo real respetando los filtros y ordenamientos activos.</li>
                        <li>Se descargará un archivo de Excel estándar en formato <strong>.xlsx</strong> con todas las columnas y filas de tu inventario.</li>
                    </ul>
                `
            }
        ]
    }
];

class TutorialsManager {
    constructor() {
        this.activeCategory = null;
        this.activeTopic = null;
        this.overlay = null;
    }

    createModal() {
        // Create element overlay
        this.overlay = document.createElement("div");
        this.overlay.className = "tutorials-modal-overlay";
        this.overlay.id = "tutorials-modal";
        
        // Assemble markup
        this.overlay.innerHTML = `
            <div class="tutorials-modal-content">
                <div class="tutorials-header">
                    <h2><i class="ph-bold ph-book-open"></i> Centro de Tutoriales y Ayuda</h2>
                    <button class="tutorials-close-btn" id="close-tutorials-btn" aria-label="Cerrar modal">&times;</button>
                </div>
                <div class="tutorials-body" id="tutorials-body-pane">
                    <div class="tutorials-sidebar" id="tutorials-sidebar-list">
                        <!-- Dynamic categories or topics lists -->
                    </div>
                    <div class="tutorials-detail" id="tutorials-detail-view">
                        <!-- Dynamic article reading pane -->
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(this.overlay);

        // Close button click listener
        this.overlay.querySelector("#close-tutorials-btn").addEventListener("click", () => this.close());
        
        // Click outside overlay listener
        this.overlay.addEventListener("click", (e) => {
            if (e.target === this.overlay) this.close();
        });

        // Keydown Esc listener
        this.escapeHandler = (e) => {
            if (e.key === "Escape") this.close();
        };
        window.addEventListener("keydown", this.escapeHandler);
    }

    open() {
        if (!this.overlay) {
            this.createModal();
        }
        
        this.overlay.classList.remove("hidden");
        this.overlay.style.display = "flex";
        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";

        // Reset state and render home list
        this.activeCategory = null;
        this.activeTopic = null;
        this.renderCategoriesList();
        this.renderEmptyDetail();
    }

    close() {
        if (this.overlay) {
            this.overlay.style.display = "none";
            this.overlay.classList.add("hidden");
            document.body.style.overflow = "";
            document.documentElement.style.overflow = "";
            
            // Remove keyboard listener
            if (this.escapeHandler) {
                window.removeEventListener("keydown", this.escapeHandler);
            }
        }
    }

    renderEmptyDetail() {
        const detailPane = document.getElementById("tutorials-detail-view");
        detailPane.innerHTML = `
            <div class="tutorials-detail-empty">
                <i class="ph-fill ph-book-open"></i>
                <h3>¡Bienvenido al Centro de Ayuda!</h3>
                <p>Selecciona una categoría de la izquierda y haz clic en un tema de interés para abrir la guía paso a paso de StockiFy.</p>
            </div>
        `;
    }

    renderCategoriesList() {
        const sidebar = document.getElementById("tutorials-sidebar-list");
        const bodyPane = document.getElementById("tutorials-body-pane");
        
        // Ensure mobile class is removed
        bodyPane.classList.remove("show-detail");

        sidebar.innerHTML = "";

        TUTORIALS_DATA.forEach(category => {
            const card = document.createElement("div");
            card.className = "tutorial-item-card";
            card.innerHTML = `
                <h4><i class="${category.icon}"></i> ${category.title}</h4>
                <p>${category.description}</p>
            `;
            card.addEventListener("click", () => {
                this.activeCategory = category;
                this.activeTopic = null; // Clear active topic
                this.renderTopicsList(category);
                this.renderEmptyDetail(); // Reset detail pane to welcome state
            });
            sidebar.appendChild(card);
        });
    }

    renderTopicsList(category) {
        const sidebar = document.getElementById("tutorials-sidebar-list");
        sidebar.innerHTML = "";

        // Add return button
        const backBtn = document.createElement("button");
        backBtn.className = "tutorial-back-btn";
        backBtn.innerHTML = `<i class="ph-bold ph-arrow-left"></i> Volver al Inicio`;
        backBtn.addEventListener("click", () => {
            this.activeCategory = null;
            this.activeTopic = null; // Clear active topic
            this.renderCategoriesList();
            this.renderEmptyDetail();
        });
        sidebar.appendChild(backBtn);

        // Add topic items
        category.topics.forEach(topic => {
            const card = document.createElement("div");
            card.className = "tutorial-item-card";
            if (this.activeTopic && this.activeTopic.id === topic.id) {
                card.classList.add("active-topic");
            }
            card.innerHTML = `
                <h4>${topic.title}</h4>
                <p>${topic.description}</p>
            `;
            card.addEventListener("click", () => {
                // Highlight active topic
                sidebar.querySelectorAll(".tutorial-item-card").forEach(c => c.classList.remove("active-topic"));
                card.classList.add("active-topic");
                
                this.activeTopic = topic;
                this.renderTopicDetail(topic);
            });
            sidebar.appendChild(card);
        });
    }

    renderTopicDetail(topic) {
        const detailPane = document.getElementById("tutorials-detail-view");
        const bodyPane = document.getElementById("tutorials-body-pane");

        // Mobile responsive switch
        bodyPane.classList.add("show-detail");

        const videoMarkup = topic.noVideo 
            ? '' 
            : (topic.videoUrl 
                ? `<div class="tutorial-video-container">
                    <h3><i class="ph-bold ph-play-circle"></i> Video Explicativo</h3>
                    <iframe class="tutorial-video-iframe" src="${topic.videoUrl}" style="width:100%; aspect-ratio:16/9; border:2px solid var(--color-black); border-radius:var(--border-radius);" allowfullscreen></iframe>
                   </div>`
                : `<div class="tutorial-video-container">
                    <h3><i class="ph-bold ph-video"></i> Video Explicativo</h3>
                    <div class="tutorial-video-placeholder" onclick="alert('Próximamente: Video tutorial interactivo paso a paso para: ' + '${topic.title}')">
                        <i class="ph-fill ph-play"></i>
                        <span>Ver Video Tutorial</span>
                        <p>(Próximamente disponible)</p>
                    </div>
                   </div>`);

        detailPane.innerHTML = `
            <div class="tutorial-mobile-header">
                <button class="tutorial-back-btn" id="mobile-back-topics-btn">
                    <i class="ph-bold ph-arrow-left"></i> Volver a los Temas
                </button>
            </div>
            <h1 class="tutorial-title">${topic.title}</h1>
            <div class="tutorial-text-content">
                ${topic.content}
            </div>
            ${videoMarkup}
        `;

        // Mobile back button action
        const mobileBackBtn = document.getElementById("mobile-back-topics-btn");
        if (mobileBackBtn) {
            mobileBackBtn.addEventListener("click", () => {
                bodyPane.classList.remove("show-detail");
            });
        }
    }
}

// Instantiate manager globally
const tutorialsManager = new TutorialsManager();

window.openTutorials = () => tutorialsManager.open();
window.closeTutorials = () => tutorialsManager.close();
