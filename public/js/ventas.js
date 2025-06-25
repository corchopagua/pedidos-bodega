// Configuración inicial
document.addEventListener('DOMContentLoaded', function() {
    configurarFechaDefecto();
    actualizarEstadisticas();
    configurarEventos();
    cargarBorrador();
    actualizarResumen();
});

function configurarEventos() {
    // Auto-guardar mientras escribe
    document.querySelectorAll('input, select, textarea').forEach(campo => {
        campo.addEventListener('input', function() {
            guardarBorrador();
            actualizarResumen();
        });
    });
    
    // Configurar selección de método de envío
    document.querySelectorAll('.envio-card').forEach(card => {
        card.addEventListener('click', function() {
            const method = this.getAttribute('data-method');
            seleccionarMetodoEnvio(method);
        });
    });
    
    // Manejo del formulario
    document.getElementById('pedidoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        enviarPedido();
    });
}

function seleccionarMetodoEnvio(metodo) {
    // Limpiar selecciones anteriores
    document.querySelectorAll('.envio-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelectorAll('input[name="metodoEnvio"]').forEach(radio => {
        radio.checked = false;
    });
    
    // Seleccionar el método clickeado
    const card = document.querySelector(`[data-method="${metodo}"]`);
    const radio = card.querySelector('input[type="radio"]');
    
    if (card && radio) {
        card.classList.add('selected');
        radio.checked = true;
        actualizarResumen();
    }
}

function cambiarCajasEstimadas(incremento) {
    const input = document.getElementById('cajasEstimadas');
    let valor = parseInt(input.value) || 1;
    valor += incremento;
    
    if (valor < 1) valor = 1;
    if (valor > 50) valor = 50;
    
    input.value = valor;
    actualizarResumen();
    guardarBorrador();
}

function actualizarResumen() {
    // Cliente
    const cliente = document.getElementById('nombreCliente').value || '-';
    document.getElementById('resumenCliente').textContent = cliente;
    
    // Método de envío
    const metodoSeleccionado = document.querySelector('input[name="metodoEnvio"]:checked');
    const metodo = metodoSeleccionado ? metodoSeleccionado.value : '-';
    document.getElementById('resumenEnvio').textContent = metodo;
    
    // Cajas
    const cajas = document.getElementById('cajasEstimadas').value || '1';
    document.getElementById('resumenCajas').textContent = cajas;
    
    // Prioridad
    const prioridad = document.getElementById('prioridad').value || 'Media';
    document.getElementById('resumenPrioridad').textContent = prioridad;
}

async function enviarPedido() {
    if (!validarFormulario()) return;
    
    // Obtener método de envío seleccionado
    const metodoEnvioSeleccionado = document.querySelector('input[name="metodoEnvio"]:checked');
    
    // Recopilar datos del formulario
    const nuevoPedido = {
        cliente: document.getElementById('nombreCliente').value.trim(),
        telefono: document.getElementById('telefono').value.trim(),
        direccion: document.getElementById('direccion').value.trim(),
        pago: document.getElementById('formaPago').value,
        prioridad: document.getElementById('prioridad').value,
        fechaEntrega: document.getElementById('fechaEntrega').value,
        montoTotal: parseFloat(document.getElementById('montoTotal').value) || 0,
        productos: document.getElementById('detallePedido').value.trim(),
        informacionAdicional: document.getElementById('informacionAdicional').value.trim(),
        metodoEnvio: metodoEnvioSeleccionado ? metodoEnvioSeleccionado.value : null,
        cajasEstimadas: parseInt(document.getElementById('cajasEstimadas').value) || 1
    };

    const pedidoId = await enviarABodega(nuevoPedido);
    if (pedidoId) {
        mostrarMensajeExito(pedidoId);
        await actualizarEstadisticas();
        setTimeout(() => limpiarFormulario(), 3000);
    }
}

function validarFormulario() {
    const cliente = document.getElementById('nombreCliente').value.trim();
    const direccion = document.getElementById('direccion').value.trim();
    const pago = document.getElementById('formaPago').value;
    const productos = document.getElementById('detallePedido').value.trim();
    const metodoEnvio = document.querySelector('input[name="metodoEnvio"]:checked');

    if (!cliente) {
        alert('⚠️ El nombre del cliente es obligatorio');
        document.getElementById('nombreCliente').focus();
        return false;
    }

    if (!direccion) {
        alert('⚠️ La dirección de entrega es obligatoria');
        document.getElementById('direccion').focus();
        return false;
    }

    if (!pago) {
        alert('⚠️ Debe seleccionar una forma de pago');
        document.getElementById('formaPago').focus();
        return false;
    }

    if (!productos) {
        alert('⚠️ Debe especificar el detalle del pedido');
        document.getElementById('detallePedido').focus();
        return false;
    }

    if (!metodoEnvio) {
        alert('⚠️ Debe seleccionar un método de envío');
        return false;
    }

    return true;
}

async function enviarABodega(pedido) {
    console.log('🔥 === DEBUG INICIADO ===');
    console.log('📦 Datos del pedido:', pedido);
    
    // Verificar CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        console.error('❌ CSRF token no encontrado');
        alert('❌ Error: Token CSRF no encontrado. Recarga la página.');
        return null;
    }
    
    console.log('✅ CSRF Token encontrado:', csrfToken.content);
    
    const datosEnvio = {
        nombreCliente: pedido.cliente,
        telefono: pedido.telefono,
        direccion: pedido.direccion,
        formaPago: pedido.pago,
        prioridad: pedido.prioridad,
        fechaEntrega: pedido.fechaEntrega,
        montoTotal: pedido.montoTotal,
        detallePedido: pedido.productos,
        informacionAdicional: pedido.informacionAdicional,
        metodoEnvio: pedido.metodoEnvio,
        cajasEstimadas: pedido.cajasEstimadas
    };
    
    console.log('📋 Datos preparados:', datosEnvio);
    
    try {
        console.log('📡 Enviando a /ventas/enviar...');
        
        const response = await fetch('/ventas/enviar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(datosEnvio)
        });
        
        console.log('📊 Status:', response.status, response.statusText);
        
        const responseText = await response.text();
        console.log('📄 Respuesta (primeros 500 chars):', responseText.substring(0, 500));
        
        if (response.status === 200 || response.status === 201) {
            try {
                const result = JSON.parse(responseText);
                console.log('✅ JSON parseado:', result);
                
                if (result.success) {
                    console.log('🎉 ¡ÉXITO! Pedido enviado');
                    return result.pedido_id;
                } else {
                    console.error('❌ Error del servidor:', result);
                    alert('❌ Error: ' + (result.message || 'Error desconocido'));
                    return null;
                }
            } catch (parseError) {
                console.error('❌ Error parseando JSON:', parseError);
                console.log('📄 Respuesta completa:', responseText);
                alert('❌ Respuesta del servidor no válida');
                return null;
            }
        } else if (response.status === 422) {
            try {
                const result = JSON.parse(responseText);
                console.error('❌ Errores de validación:', result);
                let errores = '';
                if (result.errors) {
                    Object.keys(result.errors).forEach(campo => {
                        errores += result.errors[campo].join(', ') + '\n';
                    });
                }
                alert('❌ Errores de validación:\n' + errores);
                return null;
            } catch (e) {
                console.error('❌ Error 422 no válido:', responseText);
                alert('❌ Error de validación');
                return null;
            }
        } else {
            console.error(`❌ Error HTTP ${response.status}`);
            alert(`❌ Error del servidor: ${response.status}`);
            return null;
        }
        
    } catch (networkError) {
        console.error('💥 Error de red:', networkError);
        alert('❌ Error de conexión. ¿Está Laravel funcionando?');
        return null;
    }
}

function mostrarMensajeExito(numeroPedido) {
    document.getElementById('pedidoNumero').textContent = numeroPedido;
    const mensaje = document.getElementById('successMessage');
    mensaje.style.display = 'block';
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    setTimeout(() => {
        mensaje.style.display = 'none';
    }, 5000);
}

function limpiarFormulario() {
    document.getElementById('pedidoForm').reset();
    
    // Limpiar selecciones de envío
    document.querySelectorAll('.envio-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Restaurar valores por defecto
    configurarFechaDefecto();
    document.getElementById('prioridad').value = 'Media';
    document.getElementById('cajasEstimadas').value = 1;
    
    // Limpiar storage
    localStorage.removeItem('borradorPedido');
    
    // Actualizar resumen
    actualizarResumen();
    
    // Focus al primer campo
    document.getElementById('nombreCliente').focus();
}

function configurarFechaDefecto() {
    const fechaInput = document.getElementById('fechaEntrega');
    if (fechaInput) {
        const mañana = new Date();
        mañana.setDate(mañana.getDate() + 1);
        fechaInput.value = mañana.toISOString().split('T')[0];
    }
}

async function actualizarEstadisticas() {
    try {
        // ✅ RUTA CORREGIDA
        const response = await fetch('/api/ventas/estadisticas');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('pedidosHoy').textContent = data.estadisticas.pedidos_hoy;
            document.getElementById('ventasHoy').textContent = `Q${data.estadisticas.ventas_hoy}`;
            document.getElementById('pendientes').textContent = data.estadisticas.pendientes;
            document.getElementById('cajasHoy').textContent = data.estadisticas.cajas_hoy || 0;
        }
    } catch (error) {
        console.error('Error actualizando estadísticas:', error);
    }
}

function guardarBorrador() {
    const metodoEnvioSeleccionado = document.querySelector('input[name="metodoEnvio"]:checked');
    
    const borrador = {
        cliente: document.getElementById('nombreCliente').value,
        telefono: document.getElementById('telefono').value,
        direccion: document.getElementById('direccion').value,
        pago: document.getElementById('formaPago').value,
        prioridad: document.getElementById('prioridad').value,
        fechaEntrega: document.getElementById('fechaEntrega').value,
        montoTotal: document.getElementById('montoTotal').value,
        productos: document.getElementById('detallePedido').value,
        informacionAdicional: document.getElementById('informacionAdicional').value,
        metodoEnvio: metodoEnvioSeleccionado ? metodoEnvioSeleccionado.value : null,
        cajasEstimadas: document.getElementById('cajasEstimadas').value
    };
    
    localStorage.setItem('borradorPedido', JSON.stringify(borrador));
}

function cargarBorrador() {
    const borrador = localStorage.getItem('borradorPedido');
    if (borrador) {
        try {
            const datos = JSON.parse(borrador);
            
            // Cargar campos básicos
            Object.keys(datos).forEach(key => {
                const elemento = document.getElementById(key);
                if (elemento && datos[key]) {
                    elemento.value = datos[key];
                }
            });
            
            // Restaurar método de envío seleccionado
            if (datos.metodoEnvio) {
                setTimeout(() => {
                    seleccionarMetodoEnvio(datos.metodoEnvio);
                }, 100);
            }
            
            // Actualizar resumen
            setTimeout(() => {
                actualizarResumen();
            }, 200);
            
        } catch (error) {
            console.error('Error cargando borrador:', error);
            localStorage.removeItem('borradorPedido');
        }
    }
}

// Eventos automáticos
setInterval(actualizarEstadisticas, 30000);

// Simulación de estado de conexión
setInterval(() => {
    const status = document.getElementById('connectionStatus');
    if (!status) return;
    
    const isConnected = Math.random() > 0.05;
    
    if (isConnected) {
        status.className = 'connection-status connected';
        status.innerHTML = '✓ Conectado a Sistema';
    } else {
        status.className = 'connection-status disconnected';
        status.innerHTML = '⚠ Verificando Conexión...';
    }
}, 15000);

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + Enter para enviar formulario
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('pedidoForm').dispatchEvent(new Event('submit'));
    }
    
    // Ctrl + L para limpiar formulario
    if (e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        limpiarFormulario();
    }
    
    // Ctrl + S para guardar borrador
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        guardarBorrador();
        alert('✅ Borrador guardado');
    }
});

// Validaciones en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const cajasInput = document.getElementById('cajasEstimadas');
    const montoInput = document.getElementById('montoTotal');
    
    if (cajasInput) {
        cajasInput.addEventListener('change', function() {
            let valor = parseInt(this.value) || 1;
            if (valor < 1) valor = 1;
            if (valor > 50) valor = 50;
            this.value = valor;
            actualizarResumen();
        });
    }
    
    if (montoInput) {
        montoInput.addEventListener('change', function() {
            let valor = parseFloat(this.value) || 0;
            if (valor < 0) valor = 0;
            if (valor > 999999) valor = 999999;
            this.value = valor;
        });
    }
});