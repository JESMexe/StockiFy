document.addEventListener('DOMContentLoaded', () =>{
    const passButton = document.getElementById('btn-password');
    const passFakeInput = document.getElementById('contraseña-fake');
    const passTrueInput = document.getElementById('contraseña');
    const buttonImage = document.getElementById('pass-img');

    passButton.addEventListener('click', () => {
        if (passFakeInput.className === 'input-locked') {
            passFakeInput.className += ' hidden';
            passTrueInput.className = 'input-locked';
            buttonImage.setAttribute('src', "./assets/img/password-visible.png");
        }
        else
        {
            passTrueInput.className += ' hidden';
            passFakeInput.className = 'input-locked';
            buttonImage.setAttribute('src', "./assets/img/password-hidden.png");
        }
    })
} )
