import * as api from './api.js';
import * as setup from "/../js/setupMiCuentaDropdown.js";

let desdeDate, hastaDate, todayDate, creationDate, chart, tablaID, user, fechaDesde, fechaHasta;

const greyBg = document.getElementById('grey-background');

const fechaDesdeInput = document.getElementById('fecha-desde-elegida');
const fechaHastaInput = document.getElementById('fecha-hasta-elegida');

const graficoContainer = document.getElementById('grafico-estadistica');

function getEstadisticas(){
    const estadisticasFechaContainer = document.getElementById('estadisticas-fecha');

    estadisticasFechaContainer.innerHTML = `<div class="flex-column" id="estadisticas-fecha-desde">
                                                <h4>${creationDate.day} ${creationDate.date} de ${creationDate.month} de ${creationDate.year}</h4>
                                                    <p>Desde</p>          
                                            </div>
                                            <div class="flex-column" id="estadisticas-fecha-hasta">
                                                <h4>${todayDate.day} ${todayDate.date} de ${todayDate.month} de ${todayDate.year}</h4>
                                                <p>Hasta</p>
                                            </div>`

    actualizarEstadisticas();
}

function populateTableSelect(){

    try{
        const tableData = user['databases'];
        const selectContainer = document.getElementById('select-tabla-container');
        tableData.forEach(table => {
            const tablaItem = document.createElement('p');
            tablaItem.textContent = table.name;
            tablaItem.classList.add('tabla-item', 'btn', 'btn-primary');

            tablaItem.addEventListener('click', () => {
                tablaID = table.id;
                actualizarEstadisticas(desdeDate,hastaDate,chart,tablaID);
            })

            selectContainer.appendChild(tablaItem);
        })
    } catch (error) {
        console.error('Error al obtener los datos de la tabla:', error);
    }
}

function formatDate(ogDate){
    const todayDate = ogDate.toLocaleString('es-AR', {day : 'numeric'});
    const todayDay = ogDate.toLocaleString('es-AR', {weekday : 'long'}).charAt(0).toUpperCase() + ogDate.toLocaleString('es-AR', {weekday : 'long'}).slice(1);
    const todayMonth = ogDate.toLocaleString('es-AR', {month : 'long'}).charAt(0).toUpperCase() + ogDate.toLocaleString('es-AR', {month : 'long'}).slice(1);
    const todayYear = ogDate.toLocaleString('es-AR', {year : 'numeric'});

    return {'date' : todayDate,'day' : todayDay,'month' : todayMonth,'year' : todayYear};
}

async function actualizarEstadisticas()
{
    let fechaActual = new Date(desdeDate);
    const listaFechas = [];
    while (fechaActual <= hastaDate){
        listaFechas.push(formatYMD(fechaActual));
        fechaActual.setDate(fechaActual.getDate()+1);
    }
    if (!tablaID){
        tablaID = user['databases'][0]['id'];
    }

    const dailyData = await api.updateStatistics(tablaID,listaFechas);

    if (dailyData){
        const groupedData = formatStatData(dailyData);
        addContainerData(dailyData,groupedData,listaFechas);
    }

}

function formatYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatStatData(data) {

    const categories = ['General','Table'];
    const statistics = ['stockVendido','stockIngresado','gastos','ganancia','ingresosBrutos','promedioVenta'];

    const groupedData = {};

    categories.forEach(category => {
        const categoryKey = `${category.toLowerCase()}Data`;
        groupedData[categoryKey] = {};

        statistics.forEach(statistic => {
            const statisticKey = `${statistic}${category}`;
            const dataValue = data[statisticKey].reduce((acum,valor) => {
                return acum + valor;
            })
            groupedData[categoryKey][statistic] = dataValue;
        })
    })
    return groupedData;
}

function addContainerData(dailyData, groupData, listaFechas){

    const containerConfig = [
        { id: 'ventas-general',         groupedKey: 'generalData.stockVendido',   dailyKey: 'stockVendidoGeneral',   unit: 'unit' },
        { id: 'ganancias-general',      groupedKey: 'generalData.ganancia',       dailyKey: 'gananciaGeneral',       unit: '$' },
        { id: 'ingresos-brutos-general',groupedKey: 'generalData.ingresosBrutos', dailyKey: 'ingresosBrutosGeneral', unit: '$' },
        { id: 'ingresos-stock-general', groupedKey: 'generalData.stockIngresado', dailyKey: 'stockIngresadoGeneral', unit: 'unit' },
        { id: 'gastos-general',         groupedKey: 'generalData.gastos',         dailyKey: 'gastosGeneral',         unit: '$' },
        { id: 'promedio-venta-general', groupedKey: 'generalData.promedioVenta',  dailyKey: 'promedioVentaGeneral',  unit: '$' },
        { id: 'ventas-tabla',         groupedKey: 'tableData.stockVendido',   dailyKey: 'stockVendidoTable',   unit: 'unit' },
        { id: 'ganancias-tabla',      groupedKey: 'tableData.ganancia',       dailyKey: 'gananciaTable',       unit: '$' },
        { id: 'ingresos-brutos-tabla',groupedKey: 'tableData.ingresosBrutos', dailyKey: 'ingresosBrutosTable', unit: '$' },
        { id: 'ingresos-stock-tabla', groupedKey: 'tableData.stockIngresado', dailyKey: 'stockIngresadoTable', unit: 'unit' },
        { id: 'gastos-tabla',         groupedKey: 'tableData.gastos',         dailyKey: 'gastosTable',         unit: '$' },
        { id: 'promedio-venta-tabla', groupedKey: 'tableData.promedioVenta',  dailyKey: 'promedioVentaTable',  unit: '$' }
    ];

    containerConfig.forEach(container =>{
        const statisticContainer = document.getElementById(container.id);
        const keys = container.groupedKey.split('.');

        const groupValue = groupData[keys[0]][keys[1]];
        const h3Text = (container.unit === 'unit') ? `${groupValue} unidad${(groupValue > 1) ? 'es' : ''}` :
            `$${groupValue}`;

        statisticContainer.querySelector('h3').textContent = h3Text;

        statisticContainer.addEventListener('click', () => {
            const statName = statisticContainer.querySelector('h1').textContent;
            showGraph(statName,dailyData[container.dailyKey],listaFechas,chart);
        });
    })
}

function showGraph(statName,dailyData,dateList) {
    const options = {
        chart: {
            type: 'area',
            height: 350
        },
        series: [{
            name: statName,
            data: dailyData
        }],
        xaxis: {
            categories: dateList
        }
    };
    chart.updateOptions(options);

    const graphContainer = document.getElementById('grafico-estadistica-container');
    const greyBg = document.getElementById('grey-background');
    const backBtn = graphContainer.querySelector('p');

    graphContainer.querySelector('h3').textContent = `Estadísticas Diarias = ${statName}`;
    greyBg.classList.remove('hidden');
    graphContainer.classList.remove('hidden');

    backBtn.addEventListener('click', () => {
        greyBg.classList.add('hidden');
        graphContainer.classList.add('hidden');
    })

    greyBg.addEventListener('click', () => {
        greyBg.classList.add('hidden');
        graphContainer.classList.add('hidden');
    })
}

    function getInitialDates(value){
        desdeDate = new Date(value.replace(/-/g, '\/'));
        creationDate = formatDate(desdeDate);
        hastaDate = new Date();
        todayDate = formatDate(hastaDate);
    }

function createGraph(){
    const options = {
        chart:{
            type: 'area',
            height: 350
        },
        series: [{
            name: 'empty',
            data: [0]
        }],
        xaxis: {
            categories: 'empty'
        }
    };

    chart = new ApexCharts(graficoContainer,options);
    chart.render();
}


function setupDatePickers(fechaDesdeBtn, fechaHastaBtn){
    fechaHastaInput.min = desdeDate.toISOString().slice(0,10);
    fechaDesdeInput.min = desdeDate.toISOString().slice(0,10);
    fechaDesdeInput.max = formatYMD(hastaDate);
    fechaHastaInput.max = formatYMD(hastaDate);

    console.log(fechaDesdeBtn);

    fechaDesde = creationDate;
    fechaHasta = todayDate;

    fechaDesdeBtn.addEventListener('click',function() {
        showPicker(fechaDesdeInput);
    })
    fechaHastaBtn.addEventListener('click',function() {
        showPicker(fechaHastaInput);
    })



    fechaDesdeInput.addEventListener('blur', hidePicker);

    fechaHastaInput.addEventListener('blur', hidePicker);

    greyBg.addEventListener('click', () =>{
        hidePicker();
    })

    fechaDesdeInput.addEventListener('input', function() {
        const fechaPrevia = desdeDate;

        desdeDate = (this.value === '') ? fechaPrevia : new Date(this.value.replace(/-/g, '\/'));

        fechaHastaInput.min = this.value;

        fechaDesde = formatDate(desdeDate);
        fechaDesdeBtn.querySelector('h4').textContent = `${fechaDesde.day} ${fechaDesde.date} de ${fechaDesde.month} de ${fechaDesde.year}`

        actualizarEstadisticas();
        hidePicker();
    })

    fechaHastaInput.addEventListener('input', function() {
        const fechaPrevia = hastaDate;

        hastaDate = (this.value === '') ? fechaPrevia : new Date(this.value.replace(/-/g, '\/'));

        fechaDesdeInput.max = this.value;
        fechaHasta = formatDate(hastaDate);

        fechaHastaBtn.querySelector('h4').textContent = `${fechaHasta.day} ${fechaHasta.date} de ${fechaHasta.month} de ${fechaHasta.year}`

        actualizarEstadisticas();
        hidePicker();
    })
}

function hidePicker(){
    fechaDesdeInput.classList.add('hidden');
    fechaHastaInput.classList.add('hidden');
    greyBg.classList.add('hidden');
}

function showPicker(fechaInput){
    fechaInput.classList.remove('hidden');
    greyBg.classList.remove('hidden');

    setTimeout(() => {
        fechaInput.showPicker();
    }, 0)
}

function setupHeader(){
    const nav = document.getElementById('header-nav');

    nav.innerHTML = `
            <a href="/dashboard.php" class="btn btn-primary">Ir al Panel</a> 
            <a href="/statistics.php" class="btn btn-secondary">Estadisticas</a>
            <div id="dropdown-container">
                <div class="btn btn-secondary" id="mi-cuenta-btn">Mi Cuenta</div>
                <div class="flex-column hidden" id="mi-cuenta-dropdown">
                    <a href="/configuration.php" class="btn btn-secondary">Configuración</a>
                    <a href="/configuration.php" class="btn btn-secondary">Modificaciones de Stock</a>
                    <a href="/configuration.php" class="btn btn-secondary">Soporte</a>
                    <a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
                </div>
            </div>            
        `;
    setup.setupMiCuenta();
}

//INIT
async function init(){

    const isLoggedIn = await api.checkSessionStatus();
    if (isLoggedIn) {
        user = await api.getUserProfile();
        if (user){

            setupHeader();
            const value = user['user']['created_at'];
            tablaID = user['activeInventoryId'];

            createGraph();
            getInitialDates(value);
            getEstadisticas();

            const fechaDesdeBtn = document.getElementById('estadisticas-fecha-desde');
            const fechaHastaBtn = document.getElementById('estadisticas-fecha-hasta');

            populateTableSelect();
            setupDatePickers(fechaDesdeBtn,fechaHastaBtn);
        }
    }
    else{
        window.location.href = '/logout.php';
    }
}

document.addEventListener('DOMContentLoaded', init);
