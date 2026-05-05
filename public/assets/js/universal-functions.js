export function mostrarMensaje(tipo,mensaje){
    const msjBubble = document.getElementById('msj-bubble');
    msjBubble.classList.remove('msj-error','msj-exito');
    msjBubble.classList.add(tipo);
    msjBubble.innerHTML = mensaje;
    msjBubble.style.opacity = '100';
    setTimeout(() =>{
        msjBubble.style.opacity = '0';
    }, 8000);

}

export function getWhatsAppLink(phone) {
    if (!phone) return null;
    let digits = String(phone).replace(/\D/g, '');
    if (digits.length < 8) return null;

    if (digits.startsWith('54')) {
        return `https://wa.me/${digits}`;
    }

    if (digits.startsWith('0')) {
        digits = digits.substring(1);
    }

    if (digits.length === 12 && digits.substring(2, 4) === '15') {
        digits = digits.substring(0, 2) + digits.substring(4); 
    }

    if (digits.length === 10) {
        return `https://wa.me/549${digits}`;
    } else if (digits.startsWith('9') && digits.length === 11) {
        return `https://wa.me/54${digits}`;
    }

    return `https://wa.me/${digits}`;
}