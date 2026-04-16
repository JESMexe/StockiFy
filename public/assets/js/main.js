document.addEventListener('DOMContentLoaded', function () {

    if (document.getElementById('page-index')) {
        console.log("Index Logic Loaded");

        if (typeof Swiper !== 'undefined') {
            var swiper = new Swiper(".mySwiper", {
                effect: "coverflow",
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: "auto",
                initialSlide: 1,
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

        const sections = document.querySelectorAll('.section');
        const navDots = document.querySelectorAll('.nav-dot');
        const svgWrapper = document.querySelector('.background-animation-wrapper');

        const sectionRatios = {};
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                sectionRatios[entry.target.id] = entry.isIntersecting ? entry.intersectionRatio : 0;
            });

            // Encontrar la sección más visible en este momento
            let mostVisibleId = null;
            let maxRatio = 0;
            for (const id in sectionRatios) {
                if (sectionRatios[id] > maxRatio) {
                    maxRatio = sectionRatios[id];
                    mostVisibleId = id;
                }
            }

            if (mostVisibleId) {
                // Actualizar puntos de navegación
                navDots.forEach(dot => {
                    dot.classList.toggle('active', dot.getAttribute('data-id') === mostVisibleId);
                });

                // Actualizar fondo SVG con retraso
                if (svgWrapper) {
                    if (svgWrapper._moveTimeout) clearTimeout(svgWrapper._moveTimeout);
                    svgWrapper._moveTimeout = setTimeout(() => {
                        svgWrapper.classList.remove('pos-right', 'pos-left');
                        if (mostVisibleId === 'section-hero' || mostVisibleId === 'section-pillars') {
                            svgWrapper.classList.add('pos-right');
                        } else {
                            svgWrapper.classList.add('pos-left');
                        }
                    }, 400);
                }
            }
        }, { threshold: [0, 0.2, 0.4, 0.6, 0.8, 1.0] });

        sections.forEach(s => observer.observe(s));

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

        let pricingSwiperInstance = null;

        function handlePricingCarousel() {
            const container = document.getElementById('pricing-carousel-container');
            const wrapper = document.getElementById('pricing-wrapper');
            const pagination = document.getElementById('pricing-pagination');

            if (!container || !wrapper) return;

            if (window.innerWidth <= 1024) {
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
                    
                    setTimeout(() => {
                        if (pricingSwiperInstance) pricingSwiperInstance.slideTo(1, 0);
                    }, 50);
                }
            } else {
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

        handlePricingCarousel();
        window.addEventListener('resize', handlePricingCarousel);

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