document.addEventListener('DOMContentLoaded', () => {
    btnCuenta = document.getElementById('btn-config-cuenta');
    btnModifs = document.getElementById('btn-config-modifs');
    btnSoporte = document.getElementById('btn-config-soporte');

    formCuenta = document.getElementById('form-micuenta');
    containerRegistrosModif = document.getElementById('registro-modifs-container');
    containerSoporte = document.getElementById('soporte-container');

    btnCuenta.addEventListener('click',() =>{
        btnCuenta.className = 'btn btn-option-selected';
        btnModifs.className = 'btn';
        btnSoporte.className = 'btn';
        formCuenta.classList.remove('hidden');
        containerRegistrosModif.classList.add('hidden');
        containerSoporte.classList.add('hidden');
    });

    btnModifs.addEventListener('click',() =>{
        btnModifs.className = 'btn btn-option-selected';
        btnCuenta.className = 'btn';
        btnSoporte.className = 'btn';
        formCuenta.classList.add('hidden');
        containerRegistrosModif.classList.remove('hidden');
        containerSoporte.classList.add('hidden');
    });

    btnSoporte.addEventListener('click',() =>{
        btnSoporte.className = 'btn btn-option-selected';
        btnCuenta.className = 'btn';
        btnModifs.className = 'btn';
        formCuenta.classList.add('hidden');
        containerRegistrosModif.classList.add('hidden');
        containerSoporte.classList.remove('hidden');
    });
});