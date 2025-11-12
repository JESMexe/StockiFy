// public/assets/js/theme.js
document.addEventListener('DOMContentLoaded', () => {
    const accentColors = [
        '#88C0D0',
        '#A3BE8C',
        '#EBCB8B',
        '#BF616A',
        '#B48EAD'
    ];

    document.documentElement.style.setProperty('--accent-red', '#BF616A'); // error
    document.documentElement.style.setProperty('--accent-green', '#A3BE8C'); // ok
    document.documentElement.style.setProperty('--accent-yellow', '#EBCB8B'); // warning

    const randomColor = accentColors[Math.floor(Math.random() * accentColors.length)];
    document.documentElement.style.setProperty('--accent-color', randomColor);
    const randomColorRgb = convertToRgb(randomColor);
    document.documentElement.style.setProperty('--accent-color-rgb', randomColorRgb);

    const viewContainers = document.querySelectorAll('.view-container');

    viewContainers.forEach(container => {
        container.addEventListener('mouseenter', () => {
            const randomHoverColor = accentColors[Math.floor(Math.random() * accentColors.length)];
            container.style.setProperty('--accent-color-hover', randomHoverColor);
        });
    });
});

function convertToRgb(hexColor) {
    hexColor = hexColor.replace('#', '');

    const r = parseInt(hexColor.substring(0, 2), 16);
    const g = parseInt(hexColor.substring(2, 4), 16);
    const b = parseInt(hexColor.substring(4, 6), 16);

    return `${r}, ${g}, ${b}`;
}