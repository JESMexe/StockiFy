// public/assets/js/about-us.js

document.addEventListener('DOMContentLoaded', () => {
    initAutoType();
    initFeatureSelector();
    initImageGallery();
    initFAQ();
    initPricingCarousel();
    initMarqueeTooltip();
});

/**
 * Efecto de escritura automática para el Hero
 */
function initAutoType() {
    const textElement = document.getElementById('auto-type');
    if (!textElement) return;

    const words = [
        'almacenes',
        'importadores',
        'textiles',
        'ferreteros',
        'pinturerías',
        'repuesteras',
        'emprendedores'
    ];

    let wordIndex = 0;
    let charIndex = 0;
    let isDeleting = false;
    let typeSpeed = 150;

    function type() {
        const currentWord = words[wordIndex];

        if (isDeleting) {
            textElement.textContent = currentWord.substring(0, charIndex - 1);
            charIndex--;
            typeSpeed = 100;
        } else {
            textElement.textContent = currentWord.substring(0, charIndex + 1);
            charIndex++;
            typeSpeed = 150;
        }

        if (!isDeleting && charIndex === currentWord.length) {
            isDeleting = true;
            typeSpeed = 2000; // Pausa al final de la palabra
        } else if (isDeleting && charIndex === 0) {
            isDeleting = false;
            wordIndex = (wordIndex + 1) % words.length;
            typeSpeed = 500; // Pausa antes de empezar la siguiente
        }

        setTimeout(type, typeSpeed);
    }

    setTimeout(type, 1000);
}

/**
 * Lógica del selector interactivo (Estilo Claude V3)
 */
function initFeatureSelector() {
    const tabs = document.querySelectorAll('.feature-tab');
    if (!tabs.length) return;

    const displayTitle = document.getElementById('selector-title');
    const displayDesc = document.getElementById('selector-desc');

    const featureData = {
        gestion: {
            title: 'Gestión Inteligente',
            desc: 'Olvidate de las hojas de papel o las planillas confusas. Con nuestras tablas dinámicas, manejás tu stock como si fuera un documento estructurado, pero con la potencia de una base de datos profesional. Tenés todo a mano y siempre actualizado según tus necesidades.'
        },
        movimiento: {
            title: 'Seguridad Bancaria',
            desc: 'Protegemos tu información con los más altos estándares de la industria. Cada movimiento, cada venta y cada dato sensible viaja encriptado y bajo protocolos de seguridad de grado bancario, dándote la tranquilidad de que tu negocio está en buenas manos.'
        },
        vinculos: {
            title: 'Conectividad Meta',
            desc: 'Integración fluida con el ecosistema de Meta. Enviá comprobantes por WhatsApp, gestioná contactos y sincronizá tus ventas de forma nativa. StockiFy habla el idioma de las plataformas que tus clientes ya usan.'
        },
        mirar: {
            title: 'Escalabilidad Cloudflare',
            desc: 'Nuestra arquitectura se apoya en Cloudflare para garantizar una velocidad de respuesta superior y una disponibilidad del 99.9%. Tu negocio no escala si tu sistema se detiene; con StockiFy, el crecimiento no tiene límites técnicos.'
        }
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const key = tab.dataset.feature;
            const data = featureData[key];

            if (data && displayTitle && displayDesc) {
                const contentArea = document.querySelector('.selector-content-inner');
                contentArea.style.opacity = '0';

                setTimeout(() => {
                    displayTitle.textContent = data.title;
                    displayDesc.textContent = data.desc;
                    contentArea.style.opacity = '1';
                }, 200);
            }
            
            // Sync with custom mobile dropdown
            const selectedText = document.getElementById('custom-dropdown-selected');
            const listItems = document.querySelectorAll('#custom-dropdown-list li');
            if (selectedText && listItems) {
                listItems.forEach(li => {
                    li.classList.remove('active');
                    if (li.dataset.value === key) {
                        li.classList.add('active');
                        selectedText.textContent = li.textContent;
                    }
                });
            }
        });
    });

    const customHeader = document.getElementById('custom-dropdown-header');
    const customList = document.getElementById('custom-dropdown-list');
    const selectedText = document.getElementById('custom-dropdown-selected');
    const listItems = document.querySelectorAll('#custom-dropdown-list li');

    if (customHeader && customList) {
        customHeader.addEventListener('click', () => {
            customHeader.classList.toggle('open');
            customList.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (!customHeader.contains(e.target) && !customList.contains(e.target)) {
                customHeader.classList.remove('open');
                customList.classList.remove('open');
            }
        });

        listItems.forEach(li => {
            li.addEventListener('click', () => {
                const key = li.dataset.value;
                const data = featureData[key];
                
                // Update selected text
                selectedText.textContent = li.textContent;
                
                // Update active class
                listItems.forEach(i => i.classList.remove('active'));
                li.classList.add('active');

                // Close dropdown
                customHeader.classList.remove('open');
                customList.classList.remove('open');

                if (data && displayTitle && displayDesc) {
                    const contentArea = document.querySelector('.selector-content-inner');
                    contentArea.style.opacity = '0';

                    setTimeout(() => {
                        displayTitle.textContent = data.title;
                        displayDesc.textContent = data.desc;
                        contentArea.style.opacity = '1';
                    }, 200);
                }
                
                // Sync with tabs
                tabs.forEach(t => t.classList.remove('active'));
                let matchingTab = document.querySelector(`.feature-tab[data-feature="${key}"]`);
                if (matchingTab) matchingTab.classList.add('active');
            });
        });
    }
}

/**
 * Lógica de la Galería de Imágenes interactiva con Timeouts Sincronizados
 */
function initImageGallery() {
    const galleryItems = document.querySelectorAll('.gallery-item');
    if (!galleryItems.length) return;

    const infoInner = document.getElementById('gallery-info-inner');
    const galleryTitle = document.getElementById('gallery-target-title');
    const galleryDesc = document.getElementById('gallery-target-desc');

    let currentIndex = 0;
    let nextSlideTimeout;
    let fadeOutTimeout;

    const CYCLE_TIME = 7000;
    const FADE_OUT_START = CYCLE_TIME * 0.8; // Al 80% (5.6s)

    const galleryData = [
        {
            title: 'Control de Inventario Preciso',
            desc: 'Visualizá todo tu stock con filtros avanzados. El sistema te muestra alertas visuales de bajo stock y te permite realizar ajustes globales en segundos.'
        },
        {
            title: 'Panel de Control de Ventas',
            desc: 'Seguí tus ingresos diarios y mensuales en tiempo real. Gráficos interactivos que te ayudan a entender de dónde viene tu mayor rentabilidad.'
        },
        {
            title: 'Configuraciones de Élite',
            desc: 'Personalizá cada aspecto de tu tienda. Desde la paridad del dólar MEP hasta las columnas de tus tablas, StockiFy se adapta a tu flujo de trabajo.'
        }
    ];

    function stopLogic() {
        clearTimeout(nextSlideTimeout);
        clearTimeout(fadeOutTimeout);
    }

    function updateGallery(index, direction = 'enter') {
        stopLogic();

        // 1. Limpiar estado visual de las barras
        document.querySelectorAll('.gallery-progress-fill').forEach(p => {
            p.style.transition = 'none';
            p.style.width = '0%';
        });

        // 2. Transición de textos (Fade In desde la derecha)
        if (infoInner) {
            infoInner.classList.remove('exit');
            infoInner.classList.add('enter');

            // Forzamos reflow
            void infoInner.offsetWidth;

            // Cambiamos contenido
            galleryTitle.textContent = galleryData[index].title;
            galleryDesc.textContent = galleryData[index].desc;

            // Activamos (vuelve al centro)
            infoInner.classList.remove('enter');
        }

        // 3. Activar item de la galería
        galleryItems.forEach(i => i.classList.remove('active'));
        galleryItems[index].classList.add('active');

        // 4. Iniciar barra de progreso (al 100% real)
        setTimeout(() => {
            const currentFill = galleryItems[index].querySelector('.gallery-progress-fill');
            if (currentFill) {
                currentFill.style.transition = `width ${CYCLE_TIME}ms linear`;
                currentFill.style.width = '100%';
            }
        }, 50);

        // 5. Programar Fade Out (al 80%) hacia la izquierda
        fadeOutTimeout = setTimeout(() => {
            if (infoInner) {
                infoInner.classList.add('exit');
            }
        }, FADE_OUT_START);

        // 6. Programar cambio de slide (al 100%)
        nextSlideTimeout = setTimeout(() => {
            currentIndex = (index + 1) % galleryItems.length;
            updateGallery(currentIndex);
        }, CYCLE_TIME);
    }

    // Eventos Click Manual
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', () => {
            if (index === currentIndex) return;
            currentIndex = index;
            updateGallery(currentIndex);
        });
    });

    // Inicio
    updateGallery(0);
}

/**
 * Lógica de FAQ Modular (Acordeón)
 */
function initFAQ() {
    const faqQuestions = document.querySelectorAll('.faq-question');
    if (!faqQuestions.length) return;

    faqQuestions.forEach(question => {
        question.addEventListener('click', () => {
            const item = question.closest('.faq-item');
            if (!item) return;

            const isActive = item.classList.contains('active');

            // Cerrar otros
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));

            if (!isActive) {
                item.classList.add('active');
            }
        });
    });
}

/**
 * Lógica del Tooltip del Marquee con seguimiento suave
 */
function initMarqueeTooltip() {
    const marquee = document.getElementById('trust-marquee');
    const tooltip = document.getElementById('marquee-tooltip');

    if (!marquee || !tooltip) return;

    let targetX = 0;
    let targetY = 0;
    let currentX = 0;
    let currentY = 0;
    let isMoving = false;

    function animateTooltip() {
        if (!isMoving) return;

        // Suavizado (Lerp)
        currentX += (targetX - currentX) * 0.15;
        currentY += (targetY - currentY) * 0.15;

        tooltip.style.left = `${currentX}px`;
        tooltip.style.top = `${currentY}px`;

        requestAnimationFrame(animateTooltip);
    }

    marquee.addEventListener('mousemove', (e) => {
        if (window.innerWidth <= 768) return; // En móvil se maneja por CSS
        
        targetX = e.clientX;
        targetY = e.clientY;

        if (!isMoving) {
            isMoving = true;
            // Al entrar, igualamos posicion para que no salte
            currentX = targetX;
            currentY = targetY;
            animateTooltip();
        }

        tooltip.style.opacity = '1';
        tooltip.style.visibility = 'visible';
    });

    marquee.addEventListener('mouseleave', () => {
        isMoving = false;
        if (window.innerWidth > 768) {
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        }
    });

    // Toggle en móviles con Touch/Click
    marquee.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            marquee.classList.toggle('show-tooltip');
        }
    });
}

/**
 * Inicialización del Carrusel de Precios (Swiper) para Mobile
 */
function initPricingCarousel() {
    const carouselContainer = document.getElementById('pricing-carousel-container');
    if (!carouselContainer) return;

    // Solo inicializamos Swiper si estamos en mobile/tablet
    if (window.innerWidth <= 1024) {
        const wrapper = document.getElementById('pricing-wrapper');
        const pagination = document.getElementById('pricing-pagination');

        if (wrapper) {
            carouselContainer.classList.add('swiper', 'pricingSwiper');
            wrapper.classList.add('swiper-wrapper');
            wrapper.classList.remove('pricing-wrapper'); // Quitamos grid de escritorio

            document.querySelectorAll('.pricing-card-v2').forEach(card => {
                card.classList.add('swiper-slide');
            });

            new Swiper('.pricingSwiper', {
                slidesPerView: 'auto',
                centeredSlides: true,
                spaceBetween: 30,
                pagination: {
                    el: '.pricing-pagination',
                    clickable: true,
                },
            });
        }
    }
}
