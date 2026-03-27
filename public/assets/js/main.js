/* ==========================================================================
   LÓGICA DEL INDEX (LANDING PAGE) - ACTUALIZADO
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {

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

        if (options.length > 0) {
            options.forEach(option => {
                option.addEventListener('click', () => {
                    options.forEach(opt => opt.classList.remove('active'));
                    panels.forEach(pnl => pnl.classList.remove('active'));

                    option.classList.add('active');
                    const targetId = option.getAttribute('data-option');
                    if (document.getElementById(targetId)) {
                        document.getElementById(targetId).classList.add('active');
                    }
                });
            });
        }

        // 4. PRICING CAROUSEL DEDICADO Y SEGURO (Solo Mobile)
        let pricingSwiperInstance = null;

        function handlePricingCarousel() {
            const container = document.getElementById('pricing-carousel-container');
            const wrapper = document.getElementById('pricing-wrapper');
            const pagination = document.getElementById('pricing-pagination');

            if (!container || !wrapper) return;

            // Límite de 1024px para considerarse Mobile / Tablet
            if (window.innerWidth <= 1024) {
                // Inyectar clases de Swiper para armar el Carrusel
                container.classList.add('swiper', 'pricingSwiper');
                container.style.overflow = 'hidden'; // Requerido por swiper en móvil
                wrapper.classList.add('swiper-wrapper');

                const cards = wrapper.querySelectorAll('.pricing-card-v2');
                cards.forEach(card => card.classList.add('swiper-slide'));

                if (pagination) pagination.style.display = 'block';

                if (!pricingSwiperInstance && typeof Swiper !== 'undefined') {
                    pricingSwiperInstance = new Swiper('.pricingSwiper', {
                        effect: "slide",
                        slidesPerView: "auto",
                        centeredSlides: true,
                        spaceBetween: 20,
                        initialSlide: 1, // Inicia directo en "Profesional"
                        pagination: {
                            el: ".pricing-pagination",
                            clickable: true,
                        },
                    });
                    
                    // Forzar el snap al slide 1 (Profesional)
                    setTimeout(() => {
                        if (pricingSwiperInstance) pricingSwiperInstance.slideTo(1, 0);
                    }, 50);
                }
            } else {
                // Modo PC: Destruir cualquier rastro del carrusel para no mutar el diseño Matrix
                if (pricingSwiperInstance) {
                    pricingSwiperInstance.destroy(true, true);
                    pricingSwiperInstance = null;
                }

                container.classList.remove('swiper', 'pricingSwiper');
                container.style.overflow = 'visible'; // Restaurar overflow
                wrapper.classList.remove('swiper-wrapper');
                wrapper.style.transform = ''; // Remover restos de JS

                const cards = wrapper.querySelectorAll('.pricing-card-v2');
                cards.forEach(card => {
                    card.classList.remove('swiper-slide');
                    card.style.width = '';
                    card.style.margin = '';
                });

                if (pagination) pagination.style.display = 'none';
            }
        }

        // Ejecutar en la carga y al cambiar de tamaño
        handlePricingCarousel();
        window.addEventListener('resize', handlePricingCarousel);

        // 5. Footer Year
        const yearEl = document.getElementById("year");
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    }
});

document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("imgModal");
    const modalImg = document.getElementById("imgModalContent");
    const closeBtn = document.querySelector(".img-modal-close");

    if (!modal || !modalImg || !closeBtn) return;

    document.querySelectorAll("#section-gallery .slide-img-container img").forEach(img => {
        img.style.cursor = "zoom-in";
        img.addEventListener("click", () => {
            modal.classList.add("is-open");
            modal.setAttribute("aria-hidden", "false");
            modalImg.src = img.src;
            modalImg.alt = img.alt || "Captura";
            document.body.style.overflow = "hidden";
        });
    });

    function close() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        modalImg.src = "";
        document.body.style.overflow = "";
    }

    closeBtn.addEventListener("click", close);
    modal.addEventListener("click", (e) => { if (e.target === modal) close(); });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") close(); });
});