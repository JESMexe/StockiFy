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
    document.documentElement.style.setProperty('--accent-blue', '#88C0D0'); // info
    document.documentElement.style.setProperty('--accent-violet', '#B48EAD'); // warning

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

    // --- Smart Sticky Header para Móviles ---
    const lastScrollTops = new WeakMap();
    if (!lastScrollTops.has(window)) lastScrollTops.set(window, 0);

    const header = document.querySelector('header');

    // Detectar si el usuario está en móvil
    const isMobile = () => window.innerWidth <= 768;

    const handleScroll = (scroller) => {
        if (!isMobile() || !header) return;

        const promoBar = document.querySelector('.promo-secondary-bar');
        let scrollTop = scroller === window ? window.pageYOffset : scroller.scrollTop;
        
        if (!lastScrollTops.has(scroller)) lastScrollTops.set(scroller, 0);
        let lastScrollTop = lastScrollTops.get(scroller);

        // Agregamos un umbral (sensibilidad) mayor, para evitar rebote de elastic scroll
        const delta = scrollTop - lastScrollTop;
        if (Math.abs(delta) < 40) return;

        if (delta > 0 && scrollTop > 100) {
            // Scroll Down - Ocultar
            header.classList.add('nav-hidden');
            if (promoBar) promoBar.classList.add('nav-hidden');
        } else if (delta < 0) {
            // Scroll Up - Mostrar
            header.classList.remove('nav-hidden');
            if (promoBar) promoBar.classList.remove('nav-hidden');
        }
        lastScrollTops.set(scroller, scrollTop <= 0 ? 0 : scrollTop);
    };

    // Escuchar el scroll en window
    window.addEventListener('scroll', () => handleScroll(window), { passive: true });

    // Escuchar el scroll en el contenedor especial de index.php si existe
    const mainScroller = document.getElementById('main-scroller');
    if (mainScroller) {
        lastScrollTops.set(mainScroller, 0);
        mainScroller.addEventListener('scroll', () => handleScroll(mainScroller), { passive: true });
    }
});

function convertToRgb(hexColor) {
    hexColor = hexColor.replace('#', '');

    const r = parseInt(hexColor.substring(0, 2), 16);
    const g = parseInt(hexColor.substring(2, 4), 16);
    const b = parseInt(hexColor.substring(4, 6), 16);

    return `${r}, ${g}, ${b}`;
}