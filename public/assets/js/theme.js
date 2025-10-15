// public/assets/js/theme.js
document.addEventListener('DOMContentLoaded', () => {
    // 1. Define tu paleta de colores. Sigue siendo la misma.
    const accentColors = [
        '#88C0D0',
        '#A3BE8C',
        '#EBCB8B',
        '#BF616A',
        '#B48EAD'
    ];

    // --- Lógica para el color de acento principal (al cargar la página) ---
    const randomColor = accentColors[Math.floor(Math.random() * accentColors.length)];
    document.documentElement.style.setProperty('--accent-color', randomColor);

    // --- NUEVA LÓGICA: Efecto de hover aleatorio en los contenedores ---
    const viewContainers = document.querySelectorAll('.view-container');

    viewContainers.forEach(container => {
        container.addEventListener('mouseenter', () => {
            // Cada vez que el mouse entra, elige un nuevo color al azar
            const randomHoverColor = accentColors[Math.floor(Math.random() * accentColors.length)];
            // Y lo aplica a la variable --accent-color-hover específica de ESE contenedor
            container.style.setProperty('--accent-color-hover', randomHoverColor);
        });
    });
});