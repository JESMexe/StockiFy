// public/assets/js/theme.js
document.addEventListener('DOMContentLoaded', () => {
    const accentColors = [
        '#88C0D0',
        '#A3BE8C',
        '#EBCB8B',
        '#BF616A',
        '#B48EAD'
    ];

    const randomColor = accentColors[Math.floor(Math.random() * accentColors.length)];
    document.documentElement.style.setProperty('--accent-color', randomColor);

    const viewContainers = document.querySelectorAll('.view-container');

    viewContainers.forEach(container => {
        container.addEventListener('mouseenter', () => {
            const randomHoverColor = accentColors[Math.floor(Math.random() * accentColors.length)];
            container.style.setProperty('--accent-color-hover', randomHoverColor);
        });
    });
});