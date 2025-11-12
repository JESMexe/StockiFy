document.addEventListener('DOMContentLoaded', () => {
    const formConfig = document.getElementById('form-micuenta');
    const btnGuardar = document.getElementById('btn-guardar');

    const valuesIniciales = {};

    const inputs = formConfig.querySelectorAll('input:not([disabled])');

    inputs.forEach(input => {
        valuesIniciales[input.name] = input.value;
    });

    formConfig.addEventListener('input', () =>{
        let formModificado = false;

        inputs.forEach(input => {
          if (input.value !== valuesIniciales[input.name]) {
              formModificado = true;
          }
        });

        if (formModificado){
            btnGuardar.disabled = false;
        }
        else{
            btnGuardar.disabled = true;
        }

    })
})