/* BakerSoft - JS de las pantallas de autenticación. Sin librerías.
   Por ahora: mostrar/ocultar contraseña.

   Se engancha por delegación, así que sirve para cualquier botón con
   data-toggle-password="<id del input>", en login, registro o recuperación. */

document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-toggle-password]');

    if (!boton) {
        return;
    }

    var input = document.getElementById(boton.dataset.togglePassword);

    if (!input) {
        return;
    }

    var oculta = input.type === 'password';

    input.type = oculta ? 'text' : 'password';
    boton.textContent = oculta ? 'ocultar' : 'ver';
    boton.setAttribute('aria-pressed', oculta ? 'true' : 'false');

    // Devolvemos el foco al campo, al final del texto.
    input.focus();
    var largo = input.value.length;
    input.setSelectionRange(largo, largo);
});
