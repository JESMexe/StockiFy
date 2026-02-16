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
    document.documentElement.style.setProperty('--accent-blue', '#88C0D0');
    document.documentElement.style.setProperty('--accent-violet', '#B48EAD');

    document.documentElement.style.setProperty('--accent-red-50', '#BF616A80');
    document.documentElement.style.setProperty('--accent-red-20', '#BF616A33');

    document.documentElement.style.setProperty('--accent-blue-50', '#88C0D080');
    document.documentElement.style.setProperty('--accent-blue-20', '#88C0D033');

    document.documentElement.style.setProperty('--accent-green-50', '#A3BE8C80');
    document.documentElement.style.setProperty('--accent-green-20', '#A3BE8C33');

    document.documentElement.style.setProperty('--accent-yellow-50', '#EBCB8B80');
    document.documentElement.style.setProperty('--accent-yellow-20', '#EBCB8B33');

    document.documentElement.style.setProperty('--accent-violet-50', '#B48EAD80');
    document.documentElement.style.setProperty('--accent-violet-20', '#B48EAD33');

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