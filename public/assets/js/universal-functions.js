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