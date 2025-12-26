document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const mascotContainer = document.querySelector('.login-mascot-container');
    const eyes = document.querySelectorAll('.eye');

    document.addEventListener('mousemove', (e) => {
        if (!mascotContainer || mascotContainer.classList.contains('covering')) return;

        eyes.forEach(eye => {
            const pupil = eye.querySelector('.pupil');
            const eyeRect = eye.getBoundingClientRect();
            const eyeCenterX = eyeRect.left + (eyeRect.width / 2);
            const eyeCenterY = eyeRect.top + (eyeRect.height / 2);

            const dx = e.clientX - eyeCenterX;
            const dy = e.clientY - eyeCenterY;
            const angle = Math.atan2(dy, dx);

            const maxMove = 13;
            const x = Math.cos(angle) * maxMove;
            const y = Math.sin(angle) * maxMove;

            pupil.style.transform = `translate(${x}px, ${y}px)`;
        });
    });

    if (passwordInput && mascotContainer) {
        passwordInput.addEventListener('focus', () => {
            mascotContainer.classList.add('covering');
        });

        passwordInput.addEventListener('blur', () => {
            mascotContainer.classList.remove('covering');
        });
    }
});