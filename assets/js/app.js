/* BakerSoft - JS de la aplicación. Sin librerías.
   Cada bloque se engancha por delegación y se guarda solo si encuentra sus
   propios elementos en la página, así conviven varias pantallas en un mismo
   archivo sin pisarse (mismo criterio que assets/js/auth.js). */

document.addEventListener('DOMContentLoaded', function () {
    console.log('BakerSoft cargado.');
});

/* -----------------------------------------------------------------------
   Alta de pedido (HU-09): radios de cliente, filas de producto dinámicas y
   aviso de anticipación mínima para pedidos "Evento".
   ----------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-pedido');

    if (!form) {
        return;
    }

    /* --- Cliente existente / cliente nuevo --- */

    var bloqueExistente = document.getElementById('bloque-cliente-existente');
    var bloqueNuevo = document.getElementById('bloque-cliente-nuevo');

    document.querySelectorAll('input[name="modo_cliente"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!bloqueExistente || !bloqueNuevo) {
                return;
            }

            bloqueExistente.hidden = radio.value !== 'existente';
            bloqueNuevo.hidden = radio.value !== 'nuevo';
        });
    });

    /* --- Filas de producto: agregar / quitar --- */

    var filas = document.getElementById('productos-filas');
    var btnAgregar = document.getElementById('btn-agregar-producto');
    var plantilla = document.getElementById('template-fila-producto');

    if (btnAgregar && filas && plantilla) {
        btnAgregar.addEventListener('click', function () {
            var indice = parseInt(btnAgregar.dataset.siguienteIndice, 10) || 0;
            var fila = plantilla.content.cloneNode(true);

            fila.querySelectorAll('[name]').forEach(function (campo) {
                var esSelect = campo.tagName === 'SELECT';
                campo.setAttribute('name', 'productos[' + indice + '][' + (esSelect ? 'id_producto' : 'cantidad') + ']');
            });

            filas.appendChild(fila);
            btnAgregar.dataset.siguienteIndice = String(indice + 1);
        });
    }

    if (filas) {
        filas.addEventListener('click', function (evento) {
            var boton = evento.target.closest('.abm-btn-quitar-producto');

            if (!boton) {
                return;
            }

            // Siempre queda al menos una fila; la última se vacía en vez de
            // desaparecer, para no dejar el formulario sin ningún producto.
            if (filas.children.length <= 1) {
                boton.closest('.abm-fila-producto').querySelectorAll('select, input').forEach(function (campo) {
                    campo.value = '';
                });
                return;
            }

            boton.closest('.abm-fila-producto').remove();
        });
    }

    /* --- Anticipación mínima según el tipo de pedido --- */

    var selectTipo = document.getElementById('id_tipo_pedido');
    var inputFecha = document.getElementById('fecha_entrega');
    var aviso = document.getElementById('aviso-anticipacion');
    var inputConfirmar = document.getElementById('confirmar_fuera_plazo');
    var btnModificarFecha = document.getElementById('btn-modificar-fecha');
    var btnConfirmarFueraPlazo = document.getElementById('btn-confirmar-fuera-plazo');

    function anticipacionInsuficiente() {
        if (!selectTipo || !inputFecha || !inputFecha.value) {
            return false;
        }

        var opcion = selectTipo.options[selectTipo.selectedIndex];
        var horas = opcion ? parseInt(opcion.dataset.anticipacion || '0', 10) : 0;

        if (!horas) {
            return false;
        }

        var entrega = new Date(inputFecha.value);
        var minimo = new Date(Date.now() + horas * 3600000);

        return entrega < minimo;
    }

    // Un cambio de tipo o de fecha invalida una confirmación previa: hay que
    // volver a decidir si la nueva combinación necesita el aviso.
    [selectTipo, inputFecha].forEach(function (campo) {
        if (!campo) {
            return;
        }

        campo.addEventListener('change', function () {
            if (inputConfirmar) {
                inputConfirmar.value = '';
            }

            if (aviso) {
                aviso.hidden = true;
            }
        });
    });

    if (btnModificarFecha && aviso && inputFecha) {
        btnModificarFecha.addEventListener('click', function () {
            aviso.hidden = true;
            inputFecha.focus();
        });
    }

    if (btnConfirmarFueraPlazo && aviso && inputConfirmar) {
        btnConfirmarFueraPlazo.addEventListener('click', function () {
            inputConfirmar.value = '1';
            aviso.hidden = true;
            form.submit();
        });
    }

    form.addEventListener('submit', function (evento) {
        if (!aviso || !inputConfirmar) {
            return;
        }

        if (inputConfirmar.value === '1') {
            return; // ya confirmado: se deja pasar sin volver a preguntar
        }

        if (anticipacionInsuficiente()) {
            evento.preventDefault();
            aviso.hidden = false;
            aviso.scrollIntoView({ block: 'center' });
        }
    });
});

/* -----------------------------------------------------------------------
   Listado de pedidos (HU-11): los filtros se aplican por fetch, sin recargar
   la página. El <form> sigue siendo un GET normal por si JS no llega a
   correr: en ese caso el submit no se intercepta y el filtro funciona igual
   que en Producto/Tipo de Producto, con recarga completa.
   ----------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var formFiltros = document.getElementById('form-filtros-pedido');
    var contenedorTabla = document.getElementById('pedido-tabla-contenedor');

    if (!formFiltros || !contenedorTabla) {
        return;
    }

    formFiltros.addEventListener('submit', function (evento) {
        evento.preventDefault();

        var params = new URLSearchParams(new FormData(formFiltros));
        var url = formFiltros.getAttribute('action') + '?' + params.toString();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error('respuesta no ok');
                }

                return respuesta.text();
            })
            .then(function (html) {
                contenedorTabla.innerHTML = html;
            })
            .catch(function () {
                // Si el fetch falla (red caída, etc.), se cae al comportamiento
                // normal: mandar el formulario de verdad, con recarga completa.
                formFiltros.submit();
            });
    });
});

/* -----------------------------------------------------------------------
   Cancelación de pedido (HU-12): mismo criterio que el aviso de anticipación
   de HU-09 — un bloque inline oculto por defecto, sin modal. El motivo
   "Otro" despliega la aclaración obligatoria. La validación real (motivo
   elegido, aclaración si corresponde) la hace el servidor; acá solo se
   muestra/oculta lo que corresponde.
   ----------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var btnCancelar = document.getElementById('btn-cancelar-pedido');
    var bloqueConfirmar = document.getElementById('confirmar-cancelacion');

    if (!btnCancelar || !bloqueConfirmar) {
        return;
    }

    var btnVolver = document.getElementById('btn-volver-cancelacion');
    var selectMotivo = document.getElementById('motivo_cancelacion');
    var bloqueAclaracion = document.getElementById('bloque-motivo-aclaracion');

    btnCancelar.addEventListener('click', function () {
        btnCancelar.hidden = true;
        bloqueConfirmar.hidden = false;
    });

    if (btnVolver) {
        btnVolver.addEventListener('click', function () {
            bloqueConfirmar.hidden = true;
            btnCancelar.hidden = false;
        });
    }

    if (selectMotivo && bloqueAclaracion) {
        selectMotivo.addEventListener('change', function () {
            bloqueAclaracion.hidden = selectMotivo.value !== 'Otro';
        });
    }
});
