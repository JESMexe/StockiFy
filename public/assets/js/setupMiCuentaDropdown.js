export function setupMiCuenta(){
    const miCuentaBtn = document.getElementById('mi-cuenta-btn');
    const dropdown = document.getElementById('mi-cuenta-dropdown');
    const dropdownContainer = document.getElementById('dropdown-container');

    miCuentaBtn.addEventListener('click', (e) => {
        e.stopPropagation();

        if (dropdown.classList.contains('clicked')) {
            dropdown.classList.add('hidden');
            dropdown.classList.remove('clicked');
            dropdownContainer.classList.remove('clicked');
            miCuentaBtn.classList.remove('clicked');
        }
        else{
            dropdown.classList.remove('hidden');
            dropdown.classList.add('clicked');
            dropdownContainer.classList.add('clicked');
            miCuentaBtn.classList.add('clicked');
        }
    })

    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    })

    window.addEventListener('click', () => {
        if (dropdown.classList.contains('clicked')) {
            dropdown.classList.add('hidden');
            dropdown.classList.remove('clicked');
            dropdownContainer.classList.remove('clicked');
            miCuentaBtn.classList.remove('clicked');
        }
    })
}