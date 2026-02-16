/* ==========================================================================
   LÓGICA DEL INDEX (LANDING PAGE) - ACTUALIZADO
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function() {

    // Solo ejecutar si estamos en el index
    if (document.getElementById('page-index')) {
        console.log("Index Logic Loaded");

        // 1. SWIPER CONFIGURACIÓN (Estilo Vertical/Tarjeta)
        if (typeof Swiper !== 'undefined') {
            var swiper = new Swiper(".mySwiper", {
                effect: "coverflow",
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: "auto",
                initialSlide: 1,
                // Espacio entre slides
                spaceBetween: 30,
                coverflowEffect: {
                    rotate: 0,      // IMPORTANTE: 0 rotación para que se vean rectas
                    stretch: 0,
                    depth: 0,       // Sin profundidad 3D excesiva
                    modifier: 1,
                    slideShadows: false, // Sin sombras del plugin, usamos las CSS
                },
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
            });
        }

        // 2. NAV DOTS & SVG ANIMATION (Lógica mejorada)
        const sections = document.querySelectorAll('.section');
        const navDots = document.querySelectorAll('.nav-dot');
        const svgWrapper = document.querySelector('.background-animation-wrapper');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // A. Puntos de Navegación
                    navDots.forEach(dot => {
                        dot.classList.remove('active');
                        if (dot.getAttribute('data-id') === entry.target.id) {
                            dot.classList.add('active');
                        }
                    });

                    // B. Movimiento del SVG (Usando clases CSS)
                    if (svgWrapper) {
                        // Limpiar clases
                        svgWrapper.classList.remove('pos-right', 'pos-left');

                        // Lógica: Alternar Derecha / Izquierda
                        const id = entry.target.id;
                        if (id === 'section-hero' || id === 'section-pillars') {
                            svgWrapper.classList.add('pos-right');
                        } else {
                            svgWrapper.classList.add('pos-left');
                        }
                    }
                }
            });
        }, { threshold: 0.3 }); // Threshold bajo para respuesta rápida

        sections.forEach(s => observer.observe(s));

        // 3. PESTAÑAS (About Section)
        const options = document.querySelectorAll('.about-option');
        const panels = document.querySelectorAll('.content-panel');

        if(options.length > 0) {
            options.forEach(option => {
                option.addEventListener('click', () => {
                    options.forEach(opt => opt.classList.remove('active'));
                    panels.forEach(pnl => pnl.classList.remove('active'));

                    option.classList.add('active');
                    const targetId = option.getAttribute('data-option');
                    if(document.getElementById(targetId)) {
                        document.getElementById(targetId).classList.add('active');
                    }
                });
            });
        }

        // 4. Footer Year
        const yearEl = document.getElementById("year");
        if(yearEl) yearEl.textContent = new Date().getFullYear();
    }
});