document.addEventListener('DOMContentLoaded', () => {
    const wordsToCycle = [
        "Ventas.",
        "Estadísticas.",
        "Compras.",
        "Clientes.",
        "Inventarios.",
        "Productos.",
        "Proveedores."
    ];

    const cyclingText = document.getElementById('cycling-text');

    if (cyclingText) {
        let index = 0;

        setInterval(() => {
            cyclingText.classList.add('cycle');

            setTimeout(() => {
                index = (index + 1) % wordsToCycle.length;
                cyclingText.textContent = wordsToCycle[index];
                cyclingText.classList.remove('cycle');
            },200);

        },2000);
    }
});

function getNewIndex(currentIndex,wordsToCycle){
    const randomIndex = Math.floor(Math.random() * wordsToCycle.length);

    if (currentIndex === randomIndex) {
        getNewIndex(currentIndex,wordsToCycle);
    }
    return randomIndex;
}
