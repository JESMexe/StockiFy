import { mostrarMensaje } from './universal-functions.js'
import * as setup from "./setupMiCuentaDropdown.js";

document.addEventListener('DOMContentLoaded', () =>{

    setupHeader();
    const emailForm = document.getElementById('email-form');
    const codeForm = document.getElementById('code-form');
    const saveContainer = document.getElementById('save-email-container');
    const btnSaveEmail = document.getElementById('save-email-btn');
    var newEmail;
    var emailCode;

    emailForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(this);

        const oldEmail = document.getElementById('email').value;
        const name = document.getElementById('nombre').value;

        newEmail = formData.get('new-email');
        formData.append('name',name);

        if (newEmail===oldEmail){
            mostrarMensaje('msj-error',`<h3>Ingresar un email distinto al ya asociado a su cuenta.</h3>`)
        }
        else
        {
            fetch('/../send-email-change.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    mostrarMensaje(data['success'] ? 'msj-exito' : 'msj-error', `<h3>${data.message}</h3>`);
                    if (!data.success) {
                        emailForm.reset();
                    } else {
                        emailCode = data.code;
                        emailForm.className = "hidden";
                        codeForm.className = "";
                    }
                })
        }
    })

    codeForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(this);

        //No checkeo si el parseo será válido ya que ya se realizo la verificación del
        //ingreso en el form.
        const userCode = parseInt(formData.get('code'));
        console.log(userCode);
        console.log(emailCode);

        if (userCode !== emailCode){
            mostrarMensaje('msj-error','<h3>El código ingresado es incorrecto. Verifique de nuevo.</h3>')
        }
        else{
            const newEmailSpan = document.getElementById('new-email-text');
            codeForm.className = 'hidden';
            saveContainer.className = '';
            newEmailSpan.textContent = newEmail;
        }

    })

    btnSaveEmail.addEventListener('click', () => {
        const greyBg = document.getElementById('grey-background');
        const modifFormContainer = document.getElementById('modif-form-container');
        const emailValue = document.getElementById('email');
        const btnGuardar = document.getElementById('btn-guardar');

        saveContainer.className = 'hidden';
        greyBg.className = 'hidden';
        emailForm.className = 'hidden';
        modifFormContainer.classList.add('hidden');
        emailValue.value = newEmail;
        btnGuardar.disabled = false;
    })

})

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