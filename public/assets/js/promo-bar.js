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
    if (closeBtn && promoBar) {
        closeBtn.addEventListener('click', () => {
            promoBar.style.display = 'none';
        });
    }
});
