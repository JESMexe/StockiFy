document.addEventListener('DOMContentLoaded',() =>{

    //Esta es una variable DE PRUEBA para mostrar el comportamiento de la página con los registros recibidos
    //a partir de las consultas del back-end.

    const registrosRecibidos = [{
        productName: "Remera XL",
        database: 'Remeras',
        id: 163,
        stock: 23,
        prevStock: 50,
        minStock: 30,},{

        productName: "Remera S",
        database: 'Remeras',
        id: 164,
        stock: 10,
        prevStock: 20,
        minStock: 5,}
    ]

    const greyBg = document.getElementById('grey-background');
    const infoContainer = document.getElementById('info-modif-container');
    const contenedorRegistros = document.getElementById('registro-modifs-container');

    contenedorRegistros.innerHTML = '';

    registrosRecibidos.forEach(reg => {
        //Si la accion es 'Aumento', será color verde; si es 'Baja' será rojo.
        const action = (reg.stock > reg.prevStock) ? 'Aumento' : 'Baja';
        const multStock = (reg.stock > 1) ? 'es' : '';
        const lowStock = (reg.stock < reg.minStock);
        const lowStockAlert = "./assets/img/low-stock-alert.png";

        const regHTML = ` <div class="btn flex-row text-left justify-between item-registro" data-id="${reg.id}">
                        <div class="flex-column registro-seccion-1">
                            <h2 class="nombre-producto-registro">
                                ${reg.productName}
                            </h2>
                            <h3 class="accion-stock-registro">
                                <span style="color: var(--color-gray);">${action} de stock.</span>
                            </h3>
                        </div>
                        <div class="flex-row container-stock-registro registro-seccion-2">
                            ${lowStock ? `<div class="flex-column all-center low-stock-container">
                            <img src=${lowStockAlert} alt="Alerta de bajo stock"><p class="low-stock-alert">Stock Bajo</p></div>` : ``}
                            <p ${lowStock ? `style="color: red;"` : ``}>
                                Stock actual: ${reg.stock} unidad${multStock}.
                            </p>
                        </div>
                    </div>`

        contenedorRegistros.innerHTML += regHTML;
    })

    contenedorRegistros.addEventListener('click', (event) =>{
        const clickedReg = event.target.closest('.item-registro');

        //Verifico si lo clickeado fue un registro

        if (clickedReg) {
            const id = parseInt(clickedReg.dataset.id);
            const reg = registrosRecibidos.find(reg => reg.id === id);
            const stockDif = Math.abs(reg.stock-reg.prevStock);
            const lowStock = (reg.stock < reg.minStock);
            const lowStockAlert = "./assets/img/low-stock-alert.png";

            infoContainer.innerHTML += `<h1>${reg.productName}${lowStock ? `<img src=${lowStockAlert} alt="Alerta de bajo stock" style="height: 24px">` : ``}</h1> 
                                        ${lowStock ? `<h2 style="color: red">Alerta de bajo stock!</h2>` : ``}<br><br>
                                        <div class="flex-column justify-right" id="reg-info-container">
                                            <div class="flex-column">                           
                                                <h3>ID</h3><p>${reg.id}</p>
                                            </div>
                                            <div class="flex-column">                           
                                                <h3 ${lowStock ? `style="color: red"` : ``}>Stock Actual${lowStock ? `<img alt="Alerta de bajo stock" src=${lowStockAlert}>` : ``}</h3><p>${reg.stock}</p>
                                            </div>   
                                            <div class="flex-column">                           
                                                <h3>Stock Previo</h3><p>${reg.prevStock}</p>
                                            </div>   
                                            <div class="flex-column">                           
                                                <h3>Cantidad de Stock Modificada</h3><p>${stockDif}</p>
                                            </div>  
                                            <div class="flex-column">                           
                                                <h3 ${lowStock ? `style="color: red"` : ``}>Stock Mínimo${lowStock ? `<img src=${lowStockAlert} alt="Alerta de bajo stock">` : ``}</h3><p>${reg.minStock}</p>
                                            </div>        
                                            <div class="flex-column">                           
                                                <h2>Base de datos</h2><p>${reg.database}</p>
                                            </div>                                      
                                        </div>`;
            infoContainer.classList.remove('hidden');
            greyBg.classList.remove('hidden');
        }
    })

    infoContainer.addEventListener('click', (event) => {
        if (event.target.id === 'reg-return') {
            greyBg.classList.add('hidden');
            infoContainer.classList.add('hidden');
            infoContainer.innerHTML = `<div class="flex-row justify-right" style="width: 100%"><p id="reg-return" class="return-btn">Volver</p></div>`

        }
    })
})