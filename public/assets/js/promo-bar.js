/* public/assets/js/promo-bar.js */

document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('.carousel-track');
    if (!track) return;

    const rubros = [
        "Almacenes", "Ferreterías", "Importadores", "Emprendedores", 
        "Minoristas", "Mayoristas", "Distribuidoras", "Kioscos", 
        "Librerías", "Farmacias", "Bazares", "Electrónica", 
        "Indumentaria", "Repuestos", "Calzado", "Deportes", 
        "Jugueterías", "Inmobiliarias", "Ópticas", "Joyarías"
    ];

    // Crear el contenido del carrusel
    const content = rubros.map(rubro => `<span class="carousel-item">${rubro} · </span>`).join('');
    
    // Duplicar el contenido para que el loop sea infinito sin saltos
    track.innerHTML = content + content;

    // Lógica para cerrar la barra
    const closeBtn = document.getElementById('closePromo');
    const promoBar = document.querySelector('.promo-secondary-bar');
    
    function closePromoBar() {
        promoBar.style.transition = 'opacity 0.3s ease';
        promoBar.style.opacity = '0';
        setTimeout(() => {
            promoBar.style.display = 'none';
        }, 300);
    }

    if (closeBtn && promoBar) {
        closeBtn.addEventListener('click', closePromoBar);
    }

    if (promoBar) {
        let touchStartX = 0;
        let touchStartY = 0;

        promoBar.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        promoBar.addEventListener('touchend', e => {
            const touchEndX = e.changedTouches[0].screenX;
            const touchEndY = e.changedTouches[0].screenY;
            
            // Si el deslizamiento es > 50px en X o hacia ARRIBA en Y
            if (Math.abs(touchEndX - touchStartX) > 50 || (touchStartY - touchEndY) > 40) {
                if (window.innerWidth <= 768) {
                    closePromoBar();
                }
            }
        }, { passive: true });
    }
});
