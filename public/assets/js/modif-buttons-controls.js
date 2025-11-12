document.addEventListener('DOMContentLoaded', () =>{
    const greyBg = document.getElementById('grey-background');
    const btnEmail = document.getElementById('btn-modif-email');
    const btnPass = document.getElementById('btn-modif-pass');
    const modifFormContainer = document.getElementById('modif-form-container');
    const emailForm = document.getElementById('email-form');
    const codeForm = document.getElementById('code-form');
    const returnBtn = document.getElementById('return-btn');
    const msjBubble = document.getElementById('msj-bubble');
    const saveContainer = document.getElementById('save-email-container');
    const savePassBtn = document.getElementById('save-password-btn');
    const passForm = document.getElementById('change-password-form');

    returnBtn.addEventListener('click',() => {
        savePassBtn.disabled = true;
        emailForm.className = "hidden";
        codeForm.className = "hidden";
        modifFormContainer.classList.add('hidden');
        greyBg.className = 'hidden';
        passForm.className = 'hidden';
        msjBubble.style.opacity = '0';
        saveContainer.className = 'hidden';
    });
    btnEmail.addEventListener('click', () => {
        emailForm.reset();
        codeForm.reset();
        emailForm.className = "";
        greyBg.className = "";
        modifFormContainer.classList.remove('hidden');
    })
    btnPass.addEventListener('click',() => {
        passForm.reset();
        passForm.className = "";
        greyBg.className = "";
        modifFormContainer.classList.remove('hidden');

    })
})