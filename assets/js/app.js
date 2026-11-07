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
        if (!inputFecha || !inputFecha.value) {
            return false;
        }

        // En el alta las horas salen del tipo elegido; en la edición el tipo
        // es fijo y las horas vienen en el propio campo de fecha.
        var horas;

        if (selectTipo) {
            var opcion = selectTipo.options[selectTipo.selectedIndex];
            horas = opcion ? parseInt(opcion.dataset.anticipacion || '0', 10) : 0;
        } else {
            horas = parseInt(inputFecha.dataset.anticipacion || '0', 10);
        }

        if (!horas) {
            return false;
        }

        var entrega = new Date(inputFecha.value);
        var minimo = new Date(Date.now() + horas * 3600000);

        return entrega < minimo;
    }

    function fechaPasada() {
        if (!inputFecha || !inputFecha.value) {
            return false;
        }

        return new Date(inputFecha.value) < new Date();
    }

    // En la edición, la fecha y la anticipación solo se revisan si la fecha
    // cambió respecto de la guardada (data-fecha-original). En el alta no hay
    // fecha original: siempre cuenta como cambiada.
    function fechaCambio() {
        if (!inputFecha || !inputFecha.dataset.fechaOriginal) {
            return true;
        }

        return inputFecha.value !== inputFecha.dataset.fechaOriginal;
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

    /* --- Campos obligatorios (HU-09): se revisan antes de enviar ---
       Si falta algo, se muestra el error debajo de cada campo afectado y la
       solicitud no sale hacia el servidor. Los textos son los mismos que
       devuelve PedidoController::validar(), así el usuario ve lo mismo sea
       quien sea el que detecte el error. El servidor sigue validando todo
       igual (defensa en profundidad): esto no lo reemplaza. */

    function limpiarErrores() {
        form.querySelectorAll('.abm-ayuda-error').forEach(function (msj) {
            msj.remove();
        });

        form.querySelectorAll('.abm-input-error').forEach(function (campo) {
            campo.classList.remove('abm-input-error');
        });
    }

    function marcarError(campo, mensaje, contenedor) {
        var msj = document.createElement('span');
        msj.className = 'abm-ayuda abm-ayuda-error';
        msj.textContent = mensaje;

        if (campo) {
            campo.classList.add('abm-input-error');
        }

        (contenedor || campo.closest('.abm-campo')).appendChild(msj);
    }

    // Misma regla y mismos textos que PedidoController::validarProductos():
    // número mayor a cero y, si el producto es por unidad, entero. Devuelve ''
    // si la cantidad está bien.
    function mensajeCantidad(selectProducto, cantidadTexto) {
        var valor = cantidadTexto.replace(',', '.');

        if (!/^\d+(\.\d+)?$/.test(valor) || parseFloat(valor) <= 0) {
            return 'La cantidad de cada producto debe ser un número mayor a cero';
        }

        var opcion = selectProducto.options[selectProducto.selectedIndex];

        if (opcion && opcion.dataset.unidad === 'unidad' && parseFloat(valor) % 1 !== 0) {
            return '"' + opcion.dataset.nombre + '" se vende por unidad: la cantidad debe ser un número entero';
        }

        return '';
    }

    function validarObligatorios() {
        var hayErrores = false;
        var radioNuevo = document.getElementById('modo-cliente-nuevo');
        var esClienteNuevo = radioNuevo && radioNuevo.checked;

        function revisar(id, mensaje) {
            var campo = document.getElementById(id);

            if (campo && !campo.disabled && campo.value.trim() === '') {
                marcarError(campo, mensaje);
                hayErrores = true;
            }
        }

        if (esClienteNuevo) {
            revisar('cliente_nombre', 'Este campo es obligatorio');
            revisar('cliente_tipo', 'Elegí un tipo de cliente');
        } else {
            revisar('id_cliente', 'Elegí un cliente');
        }

        revisar('id_tipo_pedido', 'Elegí un tipo de pedido');
        revisar('fecha_entrega', 'Este campo es obligatorio');

        if (filas && btnAgregar) {
            var mensajeProductos = '';
            var algunaCompleta = false;

            filas.querySelectorAll('.abm-fila-producto').forEach(function (fila) {
                var producto = fila.querySelector('select').value;
                var cantidad = fila.querySelector('input').value.trim();

                if (producto !== '' && cantidad !== '') {
                    algunaCompleta = true;

                    if (!mensajeProductos) {
                        mensajeProductos = mensajeCantidad(fila.querySelector('select'), cantidad);
                    }
                } else if (producto === '' && cantidad !== '' && !mensajeProductos) {
                    mensajeProductos = 'Elegí un producto válido en cada fila';
                } else if (producto !== '' && cantidad === '' && !mensajeProductos) {
                    mensajeProductos = 'La cantidad de cada producto debe ser un número mayor a cero';
                }
            });

            if (!mensajeProductos && !algunaCompleta) {
                mensajeProductos = 'Agregá al menos un producto';
            }

            if (mensajeProductos) {
                marcarError(null, mensajeProductos, btnAgregar.closest('.abm-campo'));
                hayErrores = true;
            }
        }

        return !hayErrores;
    }

    form.addEventListener('submit', function (evento) {
        limpiarErrores();

        if (!validarObligatorios()) {
            evento.preventDefault();

            var primerError = form.querySelector('.abm-ayuda-error');

            if (primerError) {
                primerError.scrollIntoView({ block: 'center' });
            }

            return;
        }

        if (!aviso || !inputConfirmar) {
            return;
        }

        if (!fechaCambio()) {
            return; // edición con la fecha sin tocar: no hay nada que revisar
        }

        // Una fecha pasada no es "poca anticipación" (que se puede confirmar
        // igual): el servidor siempre la rechaza (PedidoController::store(),
        // 'vencida'), así que acá no se ofrece "Confirmar igual".
        if (fechaPasada()) {
            evento.preventDefault();
            aviso.hidden = true;
            inputConfirmar.value = '';
            marcarError(inputFecha, 'La fecha de entrega no puede ser anterior a este momento.');
            form.querySelector('.abm-ayuda-error').scrollIntoView({ block: 'center' });
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

/* -----------------------------------------------------------------------
   HU-13: filtros de Próximas entregas. Es un GET normal con recarga completa
   (no usa fetch): este bloque solo avisa, antes de enviar, que "desde" no
   puede ser posterior a "hasta". El servidor valida lo mismo igual (400), así
   que esto no lo reemplaza.
   ----------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var formFiltros = document.getElementById('form-filtros-entregas');

    if (!formFiltros) {
        return;
    }

    var errorFiltros = document.getElementById('error-filtros-entregas');

    formFiltros.addEventListener('submit', function (evento) {
        var desde = formFiltros.elements['fecha_desde'].value;
        var hasta = formFiltros.elements['fecha_hasta'].value;

        if (desde !== '' && hasta !== '' && desde > hasta) {
            evento.preventDefault();
            errorFiltros.textContent = 'La fecha desde no puede ser posterior a la fecha hasta';
            errorFiltros.hidden = false;
            return;
        }

        errorFiltros.hidden = true;
    });
});

/* -----------------------------------------------------------------------
   HU-14: panel de parámetros del informe de más vendidos. Es un GET normal
   con recarga completa (no usa fetch). Este bloque solo habilita las fechas
   con "Rango personalizado" (con otro período quedan deshabilitadas y no
   viajan en la query string: las fechas efectivas las calcula el servidor) y
   avisa, antes de enviar, si faltan o están al revés. El servidor valida lo
   mismo igual (400), así que esto no lo reemplaza.
   ----------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var formInforme = document.getElementById('form-informe-mas-vendidos');

    if (!formInforme) {
        return;
    }

    var selectPeriodo = formInforme.elements['periodo'];
    var campoDesde = formInforme.elements['fecha_desde'];
    var campoHasta = formInforme.elements['fecha_hasta'];
    var errorInforme = document.getElementById('error-informe-mas-vendidos');

    function habilitarFechas() {
        var personalizado = selectPeriodo.value === 'personalizado';

        campoDesde.disabled = !personalizado;
        campoHasta.disabled = !personalizado;
    }

    selectPeriodo.addEventListener('change', habilitarFechas);
    habilitarFechas();

    formInforme.addEventListener('submit', function (evento) {
        var mensaje = '';

        if (selectPeriodo.value === 'personalizado') {
            if (campoDesde.value === '' || campoHasta.value === '') {
                mensaje = 'La fecha ingresada no es válida';
            } else if (campoDesde.value > campoHasta.value) {
                mensaje = 'La fecha desde no puede ser posterior a la fecha hasta';
            }
        }

        if (mensaje !== '') {
            evento.preventDefault();
            errorInforme.textContent = mensaje;
            errorInforme.hidden = false;
            return;
        }

        errorInforme.hidden = true;
    });
});

/* -----------------------------------------------------------------------
   Avance de estado del pedido (Sprint 5, Figura 1): mismo criterio que la
   cancelación (HU-12), un bloque inline oculto por defecto, sin modal. Sin JS
   el botón principal es un submit y avanza directo; con JS se convierte en un
   botón común que despliega la confirmación ("Confirmar" envía el form,
   "Volver" la oculta). El servidor valida todo igual (estado actual y permiso
   de cada paso), así que esto no lo reemplaza.
   ----------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var formAvanzar = document.getElementById('form-avanzar-estado');

    if (!formAvanzar) {
        return;
    }

    var btnAvanzar = document.getElementById('btn-avanzar-estado');
    var bloqueAvance = document.getElementById('confirmar-avance');
    var btnVolverAvance = document.getElementById('btn-volver-avance');

    if (!btnAvanzar || !bloqueAvance) {
        return;
    }

    btnAvanzar.type = 'button';

    btnAvanzar.addEventListener('click', function () {
        btnAvanzar.hidden = true;
        bloqueAvance.hidden = false;
    });

    if (btnVolverAvance) {
        btnVolverAvance.addEventListener('click', function () {
            bloqueAvance.hidden = true;
            btnAvanzar.hidden = false;
        });
    }
});
