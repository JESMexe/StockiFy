document.addEventListener('DOMContentLoaded',() =>
{
    const saveBtn = document.getElementById('save-password-btn');
    const changePassForm = document.getElementById('change-password-form');
    const btnGuardarTodo = document.getElementById('btn-guardar');

    changePassForm.addEventListener('input', () =>{
        const oldSavedPassword = document.getElementById('contraseña').value;
        const oldPasswordVerif = document.getElementById('old-password').value;
        const newPass = document.getElementById('new-password').value;
        const newPassConfirm = document.getElementById('confirm-new-password').value;

        saveBtn.disabled = !((oldSavedPassword === oldPasswordVerif) && (newPass === newPassConfirm) && (oldSavedPassword !== newPass) && (newPass.length > 0));
    })

    changePassForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const passValue = document.getElementById('contraseña');
        const modifFormContainer = document.getElementById('modif-form-container');
        const greyBg = document.getElementById('grey-background');

        changePassForm.classList.add('hidden');
        modifFormContainer.classList.add('hidden');
        greyBg.classList.add('hidden');
        saveBtn.disabled = true;

        passValue.value = formData.get('new-password');
        btnGuardarTodo.disabled = false;
    })

})