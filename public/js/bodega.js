// Variables globales
let pedidos = [];
let pedidosFiltrados = [];
let ultimoIdVisto = 0;
let pedidoActualModal = null;
let pedidosSeleccionados = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    inicializarSistema();
});

function inicializarSistema() {
    actualizarReloj();
    cargarPedidosDesdeVentas();
    configurarEventos();
    
    // Ejecutar limpieza inicial (silenciosa)
    setTimeout(() => {
        verificarYLimpiarRegistrosAntiguos();
    }, 5000);
    
    // Actualizar automáticamente cada 10 segundos
    setInterval(cargarPedidosDesdeVentas, 10000);
    setInterval(actualizarReloj, 1000);
    
    // Auto-limpieza cada 6 horas (silenciosa)
    setInterval(verificarYLimpiarRegistrosAntiguos, 6 * 60 * 60 * 1000);
    
    console.log('✅ Sistema de bodega inicializado con auto-limpieza activada');
}

function configurarEventos() {
    // Búsqueda en tiempo real
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filtrarTabla);
    }
    
    // Eventos de filtros - MEJORADOS
    const filtroEstado = document.getElementById('filterEstado');
    const filtroPrioridad = document.getElementById('filterPrioridad');
    const filtroMetodo = document.getElementById('filterMetodoEnvio');
    
    if (filtroEstado) {
        filtroEstado.addEventListener('change', function() {
            console.log('🔄 Filtro estado cambiado a:', this.value);
            filtrarTabla();
        });
    }
    
    if (filtroPrioridad) {
        filtroPrioridad.addEventListener('change', function() {
            console.log('🔄 Filtro prioridad cambiado a:', this.value);
            filtrarTabla();
        });
    }
    
    if (filtroMetodo) {
        filtroMetodo.addEventListener('change', function() {
            console.log('🔄 Filtro método cambiado a:', this.value);
            filtrarTabla();
        });
    }
    
    // Cerrar modales con ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            cerrarModal();
        }
    });
    
    // Cerrar modales al hacer click fuera
    window.addEventListener('click', function(event) {
        const modalEnvio = document.getElementById('modalEnvio');
        if (event.target === modalEnvio) {
            cerrarModal();
        }
    });
}

function actualizarReloj() {
    const ahora = new Date();
    const tiempo = ahora.toLocaleString('es-GT', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = tiempo;
    }
}
async function cargarPedidosDesdeVentas() {
    try {
        const response = await fetch('/api/bodega/pedidos', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Verificar pedidos nuevos ANTES de actualizar la lista
            const nuevoPedido = data.pedidos.find(p => p.id > ultimoIdVisto);
            if (nuevoPedido && ultimoIdVisto > 0) {
                mostrarAlertaNuevoPedido(nuevoPedido.id);
                ultimoIdVisto = Math.max(...data.pedidos.map(p => p.id));
            } else if (ultimoIdVisto === 0) {
                ultimoIdVisto = Math.max(...data.pedidos.map(p => p.id), 0);
            }
            
            // Actualizar datos globales
            pedidos = data.pedidos || [];
            pedidosFiltrados = [...pedidos];
            
            // Limpiar selecciones de pedidos que ya no existen
            pedidosSeleccionados = pedidosSeleccionados.filter(id => 
                pedidos.some(p => p.id === id)
            );
            
            actualizarPanelCompleto();
            actualizarEstadoConexion(true);
            
            console.log(`📦 ${pedidos.length} pedidos cargados correctamente`);
            
        } else {
            throw new Error(data.message || 'Respuesta del servidor inválida');
        }
        
    } catch (error) {
        console.error('Error cargando pedidos:', error);
        actualizarEstadoConexion(false);
        
        if (pedidos.length === 0) {
            mostrarNotificacion(`❌ Error de conexión: ${error.message}`, 'error');
        }
    }
}

function mostrarAlertaNuevoPedido(idPedido) {
    const alertElement = document.getElementById('newOrderAlert');
    const idElement = document.getElementById('newOrderId');
    
    if (alertElement && idElement) {
        idElement.textContent = idPedido;
        alertElement.style.display = 'block';
        
        setTimeout(() => {
            alertElement.style.display = 'none';
        }, 5000);
    }
}

function actualizarEstadoConexion(conectado) {
    const statusElement = document.getElementById('connectionStatus');
    if (statusElement) {
        if (conectado) {
            statusElement.className = 'connection-status connected';
            statusElement.innerHTML = '✓ Sistema Activo';
        } else {
            statusElement.className = 'connection-status disconnected';
            statusElement.innerHTML = '⚠ Error de Conexión';
        }
    }
}

function actualizarPanelCompleto() {
    actualizarEstadisticas();
    actualizarEstadisticasRapidas();
    actualizarTablaPedidos();
}

function actualizarEstadisticas() {
    const porProcesar = pedidos.filter(p => p.estado === 'Por Procesar').length;
    const preparando = pedidos.filter(p => p.estado === 'Preparando').length;
    const entregados = pedidos.filter(p => p.estado === 'Entregado').length;
    const total = pedidos.length;
    const eficiencia = total > 0 ? Math.round((entregados / total) * 100) : 0;

    animarNumero('statProcesar', porProcesar);
    animarNumero('statPreparando', preparando);
    animarNumero('statEntregados', entregados);
    animarNumero('statEficiencia', eficiencia, '%');
}

function actualizarEstadisticasRapidas() {
    const hoy = new Date().toISOString().split('T')[0];
    const pedidosHoy = pedidos.filter(p => 
        p.fecha_creacion && p.fecha_creacion.split('T')[0] === hoy
    );
    
    const pendientes = pedidos.filter(p => p.estado !== 'Entregado').length;
    const completados = pedidos.filter(p => p.estado === 'Entregado').length;
    
    const cajasDespachadas = pedidos
        .filter(p => p.estado === 'Entregado' && p.cajas_finales)
        .reduce((total, p) => total + (parseInt(p.cajas_finales) || 0), 0);
    
    actualizarElemento('quickTotal', pedidosHoy.length);
    actualizarElemento('quickPendientes', pendientes);
    actualizarElemento('quickCompletados', completados);
    actualizarElemento('quickCajasDespachadas', cajasDespachadas);
}
function animarNumero(elementId, valorFinal, sufijo = '') {
    const elemento = document.getElementById(elementId);
    if (!elemento) return;
    
    const valorActual = parseInt(elemento.textContent) || 0;
    const diferencia = valorFinal - valorActual;
    const pasos = 20;
    const incremento = diferencia / pasos;
    
    let contador = 0;
    const timer = setInterval(() => {
        contador++;
        const valorTemporal = Math.round(valorActual + (incremento * contador));
        elemento.textContent = valorTemporal + sufijo;
        
        if (contador >= pasos) {
            clearInterval(timer);
            elemento.textContent = valorFinal + sufijo;
        }
    }, 30);
}

function actualizarElemento(id, valor) {
    const elemento = document.getElementById(id);
    if (elemento) {
        elemento.textContent = valor;
    }
}

function actualizarTablaPedidos() {
    const tbody = document.getElementById('tablaPedidos');
    const contador = document.getElementById('pedidosCount');
    
    if (!tbody || !contador) return;
    
    contador.textContent = pedidosFiltrados.length;
    
    if (pedidosFiltrados.length === 0) {
        tbody.innerHTML = `
            <tr class="loading-row">
                <td colspan="8" class="loading">
                    <div class="loading-content">
                        <div class="loading-text">No hay pedidos que coincidan con los filtros</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = pedidosFiltrados
        .sort(ordenarPedidos)
        .map(generarFilaPedido)
        .join('');
}

function ordenarPedidos(a, b) {
    const prioridadOrden = { 'Urgente': 4, 'Alta': 3, 'Media': 2, 'Baja': 1 };
    
    if (prioridadOrden[a.prioridad] !== prioridadOrden[b.prioridad]) {
        return prioridadOrden[b.prioridad] - prioridadOrden[a.prioridad];
    }
    
    return new Date(b.fecha_creacion) - new Date(a.fecha_creacion);
}

function generarFilaPedido(pedido) {
    const direccionCorta = truncarTexto(pedido.direccion, 70);
    const productosCortos = truncarTexto(pedido.productos, 50);
    const fechaCreacion = new Date(pedido.fecha_creacion).toLocaleDateString('es-GT');
    
    return `
        <tr class="${pedidosSeleccionados.includes(pedido.id) ? 'selected' : ''}" 
            data-pedido-id="${pedido.id}">
            <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" 
                           ${pedidosSeleccionados.includes(pedido.id) ? 'checked' : ''} 
                           onchange="toggleSeleccionPedido(${pedido.id})">
                    <div>
                        <strong>#${pedido.id}</strong>
                        <br><small>${fechaCreacion}</small>
                    </div>
                </div>
            </td>
            <td>
                <div>
                    <strong>${pedido.cliente || 'Sin nombre'}</strong>
                    ${pedido.telefono ? `<br><small>📞 ${pedido.telefono}</small>` : ''}
                    ${generarBadgePrioridad(pedido.prioridad)}
                </div>
            </td>
            <td>
                <div>
                    <small>${direccionCorta}</small>
                    ${pedido.fecha_entrega ? `<br><small>📅 ${pedido.fecha_entrega}</small>` : ''}
                </div>
            </td>
            <td>
                <div>
                    ${pedido.forma_pago || 'No especificado'}
                    ${pedido.monto_total ? `<br><small>💰 Q${pedido.monto_total}</small>` : ''}
                </div>
            </td>
            <td>
                <small>${productosCortos}</small>
            </td>
            <td>
                ${generarInfoEnvio(pedido)}
            </td>
            <td>
                ${generarBadgeEstado(pedido.estado)}
            </td>
            <td>
                ${generarBotonesAccion(pedido)}
            </td>
        </tr>
    `;
}
function truncarTexto(texto, longitud) {
    if (!texto) return '';
    return texto.length > longitud ? texto.substring(0, longitud) + '...' : texto;
}

function generarBadgePrioridad(prioridad) {
    if (!prioridad || prioridad === 'Media') return '';
    return `<span class="priority-badge priority-${prioridad.toLowerCase()}">${prioridad}</span>`;
}

function generarBadgeEstado(estado) {
    const estadoClass = (estado || 'Por Procesar').toLowerCase().replace(' ', '');
    return `<span class="status-badge status-${estadoClass}">${estado || 'Por Procesar'}</span>`;
}

function generarInfoEnvio(pedido) {
    let html = '<div class="envio-info-container">';
    
    if (pedido.cajas_finales) {
        html += `<div class="cajas-info">📦 ${pedido.cajas_finales} caja${pedido.cajas_finales > 1 ? 's' : ''}</div>`;
    } else if (pedido.cajas_estimadas) {
        html += `<div class="cajas-info" style="color: #999;">📦 Est: ${pedido.cajas_estimadas}</div>`;
    } else {
        html += `<div class="cajas-info" style="color: #999;">📦 Sin configurar</div>`;
    }
    
    if (pedido.metodo_envio) {
        const clase = pedido.metodo_envio.toLowerCase().replace(' ', '-');
        html += `<div class="metodo-info">
            <span class="envio-badge envio-${clase}">${pedido.metodo_envio}</span>
        </div>`;
    } else {
        html += `<div class="metodo-info" style="color: #999;">🚚 Sin configurar</div>`;
    }
    
    if (pedido.numero_guia) {
        html += `<div class="guia-info">📄 ${pedido.numero_guia}</div>`;
    }
    
    html += '</div>';
    return html;
}

function generarBotonesAccion(pedido) {
    const estado = pedido.estado || 'Por Procesar';
    
    switch(estado) {
        case 'Por Procesar':
            return `
                <div class="action-buttons">
                    <button class="btn-config-envio" onclick="abrirModalConfiguracion(${pedido.id})">
                        📦 Configurar
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="verDetallePedido(${pedido.id})">
                        👁️ Ver
                    </button>
                </div>
            `;
        case 'Preparando':
            return `
                <div class="action-buttons">
                    <button class="btn btn-success btn-sm" onclick="marcarEntregado(${pedido.id})">
                        🚚 Entregar
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="abrirModalConfiguracion(${pedido.id})">
                        📝 Editar
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="verDetallePedido(${pedido.id})">
                        👁️ Ver
                    </button>
                </div>
            `;
        case 'Entregado':
            return `
                <div class="action-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="verDetallePedido(${pedido.id})">
                        👁️ Ver
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="eliminarPedido(${pedido.id})">
                        🗑️
                    </button>
                </div>
            `;
        default:
            return `
                <div class="action-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="verDetallePedido(${pedido.id})">
                        👁️ Ver
                    </button>
                </div>
            `;
    }
}
async function marcarEntregado(idPedido) {
    const pedido = pedidos.find(p => p.id === idPedido);
    if (!pedido) {
        alert('❌ Pedido no encontrado');
        return;
    }
    
    if (pedido.estado !== 'Preparando') {
        alert(`❌ Solo se pueden entregar pedidos en estado "Preparando".\nEstado actual: ${pedido.estado}`);
        return;
    }
    
    const confirmMessage = `¿Confirmar entrega del pedido #${idPedido}?\n\n` +
                          `Cliente: ${pedido.cliente}\n` +
                          `Método: ${pedido.metodo_envio || 'No especificado'}\n` +
                          `Cajas: ${pedido.cajas_finales || 'No especificado'}`;
    
    if (!confirm(confirmMessage)) return;
    
    const btn = event?.target;
    let textoOriginal = '';
    
    if (btn) {
        textoOriginal = btn.innerHTML;
        btn.innerHTML = '🚚 Entregando...';
        btn.disabled = true;
    }
    
    try {
        const response = await fetch(`/api/bodega/pedidos/${idPedido}/estado`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ 
                estado: 'Entregado',
                fecha_entrega_real: new Date().toISOString()
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await cargarPedidosDesdeVentas();
            mostrarNotificacion(`✅ Pedido #${idPedido} marcado como entregado exitosamente`, 'success');
        } else {
            throw new Error(result.message || 'Error al actualizar el estado');
        }
        
    } catch (error) {
        console.error('Error marcando como entregado:', error);
        alert(`❌ Error marcando como entregado:\n${error.message}`);
    } finally {
        if (btn) {
            setTimeout(() => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
            }, 1000);
        }
    }
}

async function eliminarPedido(idPedido) {
    if (!confirm(`¿Está seguro de eliminar el pedido #${idPedido}?\n\nEsta acción no se puede deshacer.`)) return;
    
    try {
        const response = await fetch(`/api/bodega/pedidos/${idPedido}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        if (result.success) {
            await cargarPedidosDesdeVentas();
            mostrarNotificacion(`✅ Pedido #${idPedido} eliminado correctamente`, 'success');
        } else {
            alert('❌ Error: ' + (result.message || 'No se pudo eliminar el pedido'));
        }
    } catch (error) {
        console.error('Error eliminando pedido:', error);
        alert('❌ Error de conexión. Inténtelo de nuevo.');
    }
}
// FUNCIONES DE CONTROL DE BOTONES
function actualizarDatos() {
    const btn = event?.target;
    let textoOriginal = '';
    
    if (btn) {
        textoOriginal = btn.innerHTML;
        btn.innerHTML = '🔄 Actualizando...';
        btn.disabled = true;
        btn.classList.add('loading');
    }
    
    const statusElement = document.getElementById('connectionStatus');
    if (statusElement) {
        statusElement.className = 'connection-status updating';
        statusElement.innerHTML = '🔄 Actualizando datos...';
    }
    
    cargarPedidosDesdeVentas()
        .then(() => {
            mostrarNotificacion('✅ Datos actualizados correctamente', 'success');
            
            if (statusElement) {
                statusElement.className = 'connection-status connected';
                statusElement.innerHTML = '✓ Sistema Activo';
            }
        })
        .catch(error => {
            console.error('Error actualizando datos:', error);
            mostrarNotificacion('❌ Error al actualizar datos', 'error');
            
            if (statusElement) {
                statusElement.className = 'connection-status disconnected';
                statusElement.innerHTML = '⚠ Error de Actualización';
            }
        })
        .finally(() => {
            if (btn) {
                setTimeout(() => {
                    btn.innerHTML = textoOriginal;
                    btn.disabled = false;
                    btn.classList.remove('loading');
                }, 2000);
            }
        });
}

// REEMPLAZA tu función procesarPendientes() con esta versión corregida - PARTE 1

async function procesarPendientes() {
    console.log('⚡ === INICIANDO PROCESAMIENTO DE PENDIENTES ===');
    
    // Verificar que hay pedidos cargados
    if (!window.pedidos || window.pedidos.length === 0) {
        alert('ℹ️ No hay pedidos cargados para procesar');
        return;
    }
    
    // Filtrar pedidos pendientes con múltiples variaciones
    const pendientes = window.pedidos.filter(p => {
        const estado = String(p.estado || '').trim();
        
        // Lista completa de estados que consideramos "pendientes"
        const estadosPendientes = [
            'Por Procesar',
            'POR PROCESAR', 
            'por procesar',
            'Pendiente',
            'PENDIENTE',
            'pendiente',
            'nuevo',
            'NUEVO',
            'Nuevo',
            '',
            null,
            undefined
        ];
        
        const esPendiente = estadosPendientes.includes(estado) || 
                           estado.toLowerCase().includes('pendiente') ||
                           estado.toLowerCase().includes('procesar') ||
                           !estado;
        
        if (esPendiente) {
            console.log(`✅ Pedido pendiente encontrado: #${p.id} - estado: "${estado}"`);
        }
        
        return esPendiente;
    });
    
    console.log('📊 Pedidos pendientes encontrados:', pendientes.length);
    console.log('📋 Estados únicos en BD:', [...new Set(window.pedidos.map(p => p.estado))]);
    
    if (pendientes.length === 0) {
        const estadosExistentes = [...new Set(window.pedidos.map(p => p.estado))].filter(Boolean);
        alert(`ℹ️ No hay pedidos pendientes por procesar.\n\nEstados encontrados:\n${estadosExistentes.join('\n')}\n\nBusco: "Por Procesar", "Pendiente", etc.`);
        return;
    }
    // Mostrar detalles de lo que se va a procesar
    const detallePendientes = pendientes.slice(0, 10).map(p => `#${p.id} - ${p.cliente || 'Sin cliente'} (${p.estado || 'Sin estado'})`).join('\n');
    const masDetalle = pendientes.length > 10 ? `\n... y ${pendientes.length - 10} más` : '';
    
    const confirmMessage = `¿Procesar automáticamente ${pendientes.length} pedidos pendientes?\n\n` +
                          `Esto cambiará su estado a "Preparando" y asignará:\n` +
                          `• 1 caja por defecto (si no está configurado)\n` +
                          `• Método de envío "Guatex" (si no está configurado)\n\n` +
                          `Pedidos a procesar:\n${detallePendientes}${masDetalle}\n\n` +
                          `⚠️ Esta acción afectará ${pendientes.length} pedidos.`;
    
    if (!confirm(confirmMessage)) {
        console.log('❌ Operación cancelada por el usuario');
        return;
    }
    
    // Obtener referencia al botón y cambiar su estado
    const btn = event?.target;
    let textoOriginal = '';
    
    if (btn) {
        textoOriginal = btn.innerHTML;
        btn.innerHTML = '⚡ Procesando...';
        btn.disabled = true;
        btn.style.opacity = '0.6';
    }
    
    try {
        console.log('📡 Enviando solicitud al servidor...');
        console.log('🎯 IDs a procesar:', pendientes.map(p => p.id));
        const response = await fetch('/api/bodega/procesar-pendientes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                pedidos_ids: pendientes.map(p => p.id),
                configuracion_automatica: {
                    cajas_finales: 1,
                    metodo_envio: 'Guatex',
                    estado: 'Preparando'
                },
                debug_estados: pendientes.map(p => ({id: p.id, estado: p.estado}))
            })
        });
        
        console.log('📡 Respuesta del servidor:', response.status, response.statusText);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.log('❌ Error del servidor:', errorText);
            throw new Error(`Error HTTP ${response.status}: ${response.statusText}\n${errorText}`);
        }
        
        const result = await response.json();
        console.log('📊 Resultado completo:', result);
        
        if (result.success) {
            // Recargar datos desde el servidor
            await cargarPedidosDesdeVentas();
            
            const procesados = result.procesados || pendientes.length;
            const mensaje = `✅ ${procesados} pedidos procesados automáticamente y listos para envío`;
            
            mostrarNotificacion(mensaje, 'success');
            console.log('✅ Procesamiento completado exitosamente');
            
            // Mostrar estadísticas actualizadas
            setTimeout(() => {
                const totalRestante = window.pedidos ? window.pedidos.length : 0;
                console.log(`📊 Total de pedidos: ${totalRestante}`);
                alert(`✅ Procesamiento completado!\n\n• Procesados: ${procesados}\n• Estado: Preparando\n• Método: Guatex\n• Cajas: 1 por defecto`);
            }, 1000);
            
        } else {
            throw new Error(result.message || 'Error desconocido del servidor');
        }
        
    } catch (error) {
        console.error('❌ Error completo:', error);
        
        const mensajeError = `❌ Error durante el procesamiento automático:\n\n${error.message}\n\nRevisa la consola para más detalles.`;
        alert(mensajeError);
        
    } finally {
        // Restaurar botón
        if (btn) {
            setTimeout(() => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
                btn.style.opacity = '1';
            }, 1500);
        }
        
        console.log('⚡ === PROCESAMIENTO FINALIZADO ===\n');
    }
}



// REEMPLAZA COMPLETAMENTE tu función limpiarEntregados() con esta versión

async function limpiarEntregados() {
    console.log('🗑️ === INICIANDO LIMPIEZA DE ENTREGADOS ===');
    
    // Verificar múltiples variables donde pueden estar los pedidos
    let pedidosActuales = null;
    
    if (window.pedidos && window.pedidos.length > 0) {
        pedidosActuales = window.pedidos;
        console.log('✅ Pedidos encontrados en window.pedidos:', pedidosActuales.length);
    } else if (pedidos && pedidos.length > 0) {
        pedidosActuales = pedidos;
        console.log('✅ Pedidos encontrados en variable pedidos:', pedidosActuales.length);
    } else if (pedidosFiltrados && pedidosFiltrados.length > 0) {
        pedidosActuales = pedidosFiltrados;
        console.log('✅ Pedidos encontrados en pedidosFiltrados:', pedidosActuales.length);
    }
    
    // Si no encontramos pedidos, intentar cargarlos
    if (!pedidosActuales) {
        console.log('📡 No hay pedidos en memoria, intentando cargar...');
        try {
            await cargarPedidosDesdeVentas();
            if (window.pedidos && window.pedidos.length > 0) {
                pedidosActuales = window.pedidos;
                console.log('✅ Pedidos cargados exitosamente:', pedidosActuales.length);
            } else {
                throw new Error('No se pudieron cargar pedidos');
            }
        } catch (error) {
            console.error('❌ Error cargando pedidos:', error);
            alert('❌ No se pudieron cargar los pedidos.\n\nPor favor, actualiza la página y vuelve a intentar.');
            return;
        }
    }
    
    console.log('📊 Total de pedidos disponibles:', pedidosActuales.length);
    
    // Mostrar TODOS los pedidos y sus estados para debug
    console.log('🔍 === DEBUG DE TODOS LOS PEDIDOS ===');
    pedidosActuales.forEach((p, index) => {
        console.log(`Pedido ${index + 1}: ID=${p.id}, Estado="${p.estado}", Cliente="${p.cliente}"`);
    });
    
    // Buscar pedidos entregados con MÁXIMA flexibilidad
    const entregados = pedidosActuales.filter(p => {
        const estado = String(p.estado || '').trim();
        
        // Detectar CUALQUIER variación de "entregado"
        const esEntregado = 
            estado === 'ENTREGADO' ||
            estado === 'Entregado' ||
            estado === 'entregado' ||
            estado.toUpperCase() === 'ENTREGADO' ||
            estado.toLowerCase() === 'entregado' ||
            estado.includes('ENTREGADO') ||
            estado.includes('Entregado') ||
            estado.includes('entregado') ||
            estado.includes('DELIVERED') ||
            estado.includes('delivered') ||
            estado.includes('COMPLETED') ||
            estado.includes('completed');
        
        if (esEntregado) {
            console.log(`✅ PEDIDO ENTREGADO: #${p.id} - "${estado}" - ${p.cliente}`);
        }
        
        return esEntregado;
    });
    
    console.log('📋 TOTAL ENTREGADOS ENCONTRADOS:', entregados.length);
    
    if (entregados.length === 0) {
        const estadosUnicos = [...new Set(pedidosActuales.map(p => p.estado))];
        console.log('❌ No se encontraron pedidos entregados');
        console.log('📊 Estados únicos en BD:', estadosUnicos);
        
        alert(`ℹ️ No hay pedidos entregados para eliminar.\n\nTotal de pedidos: ${pedidosActuales.length}\n\nEstados encontrados:\n${estadosUnicos.join('\n')}`);
        return;
    }
    
    // Mostrar confirmación con detalles
    const detalleEntregados = entregados.slice(0, 10).map(p => 
        `#${p.id} - ${p.cliente || 'Sin cliente'} - Estado: "${p.estado}"`
    ).join('\n');
    
    const masDetalle = entregados.length > 10 ? `\n... y ${entregados.length - 10} más` : '';
    
    const confirmMessage = `🗑️ ¿ELIMINAR PEDIDOS ENTREGADOS?\n\n` +
                          `Se eliminarán ${entregados.length} pedidos de ${pedidosActuales.length} totales:\n\n` +
                          `${detalleEntregados}${masDetalle}\n\n` +
                          `⚠️ ESTA ACCIÓN NO SE PUEDE DESHACER\n` +
                          `⚠️ SE ELIMINARÁN DE LA BASE DE DATOS`;
    
    if (!confirm(confirmMessage)) {
        console.log('❌ Operación cancelada por el usuario');
        return;
    }
    
    // Proceder con la eliminación
    const btn = event?.target;
    let textoOriginal = '';
    
    if (btn) {
        textoOriginal = btn.innerHTML;
        btn.innerHTML = '🗑️ Eliminando...';
        btn.disabled = true;
        btn.style.opacity = '0.6';
    }
    
    try {
        console.log('📡 Enviando solicitud de eliminación...');
        
        const response = await fetch('/api/bodega/limpiar-entregados', {
            method: 'POST',  // <- CAMBIADO A POST
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                pedidos_ids: entregados.map(p => p.id),
                confirmar_eliminacion: true,
                accion: 'eliminar_entregados',  // Identificador adicional
                debug_info: {
                    total_pedidos: pedidosActuales.length,
                    entregados_encontrados: entregados.length,
                    estados_entregados: entregados.map(p => ({
                        id: p.id, 
                        estado: p.estado,
                        cliente: p.cliente
                    }))
                }
            })
        });
        
        console.log('📡 Respuesta del servidor:', response.status, response.statusText);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.log('❌ Error del servidor:', errorText);
            throw new Error(`Error HTTP ${response.status}: ${errorText}`);
        }
        
        const result = await response.json();
        console.log('📊 Resultado:', result);
        
        if (result.success) {
            // Recargar datos
            await cargarPedidosDesdeVentas();
            
            const eliminados = result.eliminados || entregados.length;
            mostrarNotificacion(`🗑️ ${eliminados} pedidos entregados eliminados correctamente`, 'success');
            
            setTimeout(() => {
                alert(`✅ LIMPIEZA EXITOSA!\n\n• Eliminados: ${eliminados}\n• Restantes: ${(pedidosActuales.length - eliminados)}\n• Base de datos actualizada`);
            }, 1000);
            
        } else {
            throw new Error(result.message || 'Error del servidor');
        }
        
    } catch (error) {
        console.error('❌ Error:', error);
        alert(`❌ ERROR AL LIMPIAR:\n\n${error.message}\n\nRevisa la consola del navegador para más detalles.`);
        
    } finally {
        if (btn) {
            setTimeout(() => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
                btn.style.opacity = '1';
            }, 1500);
        }
        
        console.log('🗑️ === LIMPIEZA FINALIZADA ===');
    }
}

async function ajustarCajasMasivo(cajas) {
    try {
        const response = await fetch('/api/bodega/ajustar-cajas-masivo', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                pedidos_ids: pedidosSeleccionados,
                cajas_finales: cajas
            })
        });
        
        const result = await response.json();
        if (result.success) {
            await cargarPedidosDesdeVentas();
            mostrarNotificacion(`✅ ${result.actualizados} pedidos actualizados con ${cajas} cajas`, 'success');
            pedidosSeleccionados = [];
        } else {
            alert('❌ Error: ' + (result.message || 'No se pudo ajustar las cajas'));
        }
    } catch (error) {
        console.error('Error ajustando cajas:', error);
        alert('❌ Error de conexión. Inténtelo de nuevo.');
    }
}


function exportarExcel() {
    console.log('📊 === INICIANDO EXPORTACIÓN EXCEL ===');
    
    // Verificar múltiples variables donde pueden estar los pedidos
    let pedidosActuales = null;
    
    if (window.pedidos && window.pedidos.length > 0) {
        pedidosActuales = window.pedidos;
        console.log('✅ Pedidos encontrados en window.pedidos:', pedidosActuales.length);
    } else if (pedidos && pedidos.length > 0) {
        pedidosActuales = pedidos;
        console.log('✅ Pedidos encontrados en variable pedidos:', pedidosActuales.length);
    } else if (pedidosFiltrados && pedidosFiltrados.length > 0) {
        pedidosActuales = pedidosFiltrados;
        console.log('✅ Pedidos encontrados en pedidosFiltrados:', pedidosActuales.length);
    }
    
    // Si no encontramos pedidos, mostrar información y intentar cargar
    if (!pedidosActuales || pedidosActuales.length === 0) {
        console.log('❌ No hay pedidos en memoria');
        console.log('🔍 Variables disponibles:');
        console.log('- window.pedidos:', window.pedidos ? window.pedidos.length : 'undefined');
        console.log('- pedidos:', typeof pedidos !== 'undefined' ? pedidos.length : 'undefined');
        console.log('- pedidosFiltrados:', typeof pedidosFiltrados !== 'undefined' ? pedidosFiltrados.length : 'undefined');
        
        // Intentar cargar datos automáticamente
        alert('⚠️ No hay pedidos cargados para exportar.\n\nVoy a intentar cargar los pedidos automáticamente...');
        
        // Intentar cargar pedidos
        cargarPedidosDesdeVentas().then(() => {
            console.log('✅ Pedidos cargados, reintentando exportación...');
            // Reintenta la exportación después de cargar
            setTimeout(() => {
                exportarExcel();
            }, 1000);
        }).catch(error => {
            console.error('❌ Error cargando pedidos:', error);
            alert('❌ No se pudieron cargar los pedidos.\n\nPor favor:\n1. Actualiza la página (F5)\n2. Espera a que carguen los pedidos\n3. Vuelve a intentar la exportación');
        });
        
        return;
    }
    
    console.log('📋 Preparando exportación de', pedidosActuales.length, 'pedidos');
    
    // Obtener referencia al botón y cambiar su estado
    const btn = event?.target;
    let textoOriginal = '';
    
    if (btn) {
        textoOriginal = btn.innerHTML;
        btn.innerHTML = '📊 Generando CSV...';
        btn.disabled = true;
        btn.style.opacity = '0.6';
    }
    
    try {
        // Preparar parámetros de exportación
        const fechaHoy = new Date().toISOString().split('T')[0];
        const timestamp = Date.now();
        const horaActual = new Date().toLocaleTimeString('es-GT', {hour12: false}).replace(/:/g, '-');
        
        // Estadísticas de los pedidos a exportar
        const stats = {
            total: pedidosActuales.length,
            entregados: pedidosActuales.filter(p => {
                const estado = String(p.estado || '').toLowerCase();
                return estado.includes('entregado') || estado.includes('delivered');
            }).length,
            preparando: pedidosActuales.filter(p => {
                const estado = String(p.estado || '').toLowerCase();
                return estado.includes('preparando') || estado.includes('preparing');
            }).length,
            pendientes: pedidosActuales.filter(p => {
                const estado = String(p.estado || '').toLowerCase();
                return estado.includes('procesar') || estado.includes('pendiente') || !estado;
            }).length
        };
        
        console.log('📈 Estadísticas de exportación:', stats);
        
        // Construir URL con parámetros - CAMBIADO A CSV
        const params = new URLSearchParams({
            formato: 'csv',  // CAMBIADO de 'xlsx' a 'csv'
            fecha: fechaHoy,
            timestamp: timestamp,
            total_pedidos: pedidosActuales.length,
            entregados: stats.entregados,
            preparando: stats.preparando,
            pendientes: stats.pendientes
        });
        
        const url = `/bodega/exportar?${params.toString()}`;
        
        console.log('📡 URL de exportación:', url);
        console.log('📅 Fecha de exportación:', fechaHoy);
        console.log('⏰ Timestamp:', timestamp);
        
        // Crear enlace de descarga directo (método más confiable)
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.download = `pedidos_bodega_${fechaHoy}_${horaActual}.csv`;  // CAMBIADO a .csv
        
        // Agregar al DOM temporalmente
        document.body.appendChild(link);
        
        // Simular click para iniciar descarga
        console.log('🔗 Iniciando descarga CSV...');
        link.click();
        
        // Remover del DOM después de un breve delay
        setTimeout(() => {
            if (document.body.contains(link)) {
                document.body.removeChild(link);
            }
        }, 100);
        
        // Mostrar notificación de éxito
        const mensaje = `📊 Exportación CSV iniciada\n\n• Total de pedidos: ${stats.total}\n• Entregados: ${stats.entregados}\n• En preparación: ${stats.preparando}\n• Pendientes: ${stats.pendientes}\n\nEl archivo CSV se abrirá en Excel automáticamente.`;
        
        mostrarNotificacion(`📊 Exportando ${stats.total} pedidos a CSV...`, 'success');
        console.log('✅ Exportación CSV iniciada correctamente');
        
        // Mostrar estadísticas detalladas después de un delay
        setTimeout(() => {
            if (confirm('✅ Exportación CSV iniciada!\n\n¿Deseas ver las estadísticas detalladas?')) {
                alert(mensaje);
            }
        }, 1500);
        
    } catch (error) {
        console.error('❌ Error exportando CSV:', error);
        
        const mensajeError = `❌ Error al generar reporte CSV:\n\n${error.message}\n\nPosibles soluciones:\n• Verifica que el endpoint /bodega/exportar existe\n• Revisa la conexión a internet\n• Intenta recargar la página\n\nRevisa la consola para más detalles.`;
        
        alert(mensajeError);
        mostrarNotificacion('❌ Error al generar reporte CSV', 'error');
        
    } finally {
        // Restaurar botón después de un breve delay
        if (btn) {
            setTimeout(() => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
                btn.style.opacity = '1';
            }, 2000);
        }
        
        console.log('📊 === EXPORTACIÓN CSV FINALIZADA ===\n');
    }
}

function generarReporte() {
    const total = pedidos.length;
    const entregados = pedidos.filter(p => p.estado === 'Entregado').length;
    const preparando = pedidos.filter(p => p.estado === 'Preparando').length;
    const procesando = pedidos.filter(p => p.estado === 'Por Procesar').length;
    
    const porGuatex = pedidos.filter(p => p.metodo_envio === 'Guatex').length;
    const porCargo = pedidos.filter(p => p.metodo_envio === 'Cargo Expreso').length;
    const porLocal = pedidos.filter(p => p.metodo_envio === 'Entrega Local').length;
    const sinMetodo = pedidos.filter(p => !p.metodo_envio).length;
    
    const totalCajas = pedidos.reduce((acc, p) => acc + (parseInt(p.cajas_finales) || 0), 0);
    const cajasEntregadas = pedidos
        .filter(p => p.estado === 'Entregado')
        .reduce((acc, p) => acc + (parseInt(p.cajas_finales) || 0), 0);
    
    const hoy = new Date().toISOString().split('T')[0];
    const pedidosHoy = pedidos.filter(p => 
        p.fecha_creacion && p.fecha_creacion.split('T')[0] === hoy
    );
    
    const urgentes = pedidos.filter(p => p.prioridad === 'Urgente').length;
    const altas = pedidos.filter(p => p.prioridad === 'Alta').length;
    
    const eficiencia = total > 0 ? Math.round((entregados / total) * 100) : 0;
    const promedioCajas = total > 0 ? (totalCajas / total).toFixed(1) : 0;
    
    const reporte = `📊 REPORTE DETALLADO DE BODEGA
    
🕐 Generado: ${new Date().toLocaleString('es-GT')}

📈 ESTADÍSTICAS GENERALES:
• Total de pedidos: ${total}
• Por procesar: ${procesando}
• En preparación: ${preparando}  
• Entregados: ${entregados}
• Eficiencia: ${eficiencia}%

📅 ESTADÍSTICAS DE HOY:
• Pedidos de hoy: ${pedidosHoy.length}
• Completados hoy: ${pedidosHoy.filter(p => p.estado === 'Entregado').length}

🚚 DISTRIBUCIÓN POR MÉTODO DE ENVÍO:
• Guatex: ${porGuatex} pedidos
• Cargo Expreso: ${porCargo} pedidos
• Entrega Local: ${porLocal} pedidos
• Sin configurar: ${sinMetodo} pedidos

⚡ PRIORIDADES:
• Urgentes: ${urgentes} pedidos
• Alta prioridad: ${altas} pedidos

📦 ESTADÍSTICAS DE CAJAS:
• Total de cajas gestionadas: ${totalCajas}
• Cajas entregadas: ${cajasEntregadas}
• Promedio por pedido: ${promedioCajas} cajas

🎯 INDICADORES CLAVE:
• Tasa de entrega: ${eficiencia}%
• Pedidos pendientes: ${procesando + preparando}
• Productividad: ${cajasEntregadas} cajas despachadas`;

    const ventanaReporte = window.open('', '_blank', 'width=600,height=700,scrollbars=yes');
    ventanaReporte.document.write(`
        <html>
        <head>
            <title>Reporte Detallado de Bodega</title>
            <style>
                body { font-family: monospace; padding: 20px; background: #f5f5f5; }
                pre { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .header { text-align: center; color: #2c5530; margin-bottom: 20px; }
                .print-btn { 
                    background: #4CAF50; color: white; border: none; padding: 10px 20px; 
                    border-radius: 4px; cursor: pointer; margin: 10px; 
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>📊 REPORTE DETALLADO DE BODEGA</h2>
            </div>
            <button class="print-btn" onclick="window.print()">🖨️ Imprimir</button>
            <button class="print-btn" onclick="window.close()">❌ Cerrar</button>
            <pre>${reporte}</pre>
        </body>
        </html>
    `);
    
    mostrarNotificacion('📄 Reporte generado en nueva ventana', 'info');
}

// AUTO-LIMPIEZA DE REGISTROS
async function verificarYLimpiarRegistrosAntiguos() {
    try {
        const response = await fetch('/api/bodega/limpiar-antiguos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                dias_antiguedad: 3,
                solo_entregados: true
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.eliminados > 0) {
            console.log(`🗑️ Auto-limpieza: ${result.eliminados} registros antiguos eliminados`);
            return result.eliminados;
        }
        
        return 0;
    } catch (error) {
        console.error('Error en auto-limpieza:', error);
        return 0;
    }
}

// FUNCIÓN DE NOTIFICACIONES MEJORADA
function mostrarNotificacion(mensaje, tipo = 'info') {
    console.log(`[${tipo.toUpperCase()}] ${mensaje}`);
    
    if (tipo === 'error') {
        alert(mensaje);
        return;
    }
    
    const notificacion = document.createElement('div');
    notificacion.className = `notification notification-${tipo}`;
    notificacion.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${tipo === 'success' ? '✅' : tipo === 'warning' ? '⚠️' : 'ℹ️'}</span>
            <span class="notification-message">${mensaje}</span>
        </div>
    `;
    
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${tipo === 'success' ? '#d4edda' : tipo === 'warning' ? '#fff3cd' : '#d1ecf1'};
        color: ${tipo === 'success' ? '#155724' : tipo === 'warning' ? '#856404' : '#0c5460'};
        border: 1px solid ${tipo === 'success' ? '#c3e6cb' : tipo === 'warning' ? '#ffeaa7' : '#bee5eb'};
        border-radius: 8px;
        padding: 12px 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 10000;
        min-width: 300px;
        max-width: 500px;
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(notificacion);
    
    setTimeout(() => {
        if (notificacion.parentNode) {
            notificacion.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.parentNode.removeChild(notificacion);
                }
            }, 300);
        }
    }, 4000);
}

// FUNCIONES DEL MODAL (simplificadas, sin configuración rápida)
function verDetallePedido(idPedido) {
    const pedido = pedidos.find(p => p.id === idPedido);
    if (!pedido) {
        alert('❌ Pedido no encontrado');
        return;
    }
    
    const fechaCreacion = new Date(pedido.fecha_creacion).toLocaleString('es-GT');
    
    const detalle = `📋 DETALLE DEL PEDIDO #${pedido.id}

👤 CLIENTE: ${pedido.cliente || 'No especificado'}
📞 TELÉFONO: ${pedido.telefono || 'No especificado'}  
📍 DIRECCIÓN: ${pedido.direccion || 'No especificada'}

💳 PAGO: ${pedido.forma_pago || 'No especificado'}
💰 MONTO: ${pedido.monto_total ? 'Q' + pedido.monto_total : 'No especificado'}

📦 PRODUCTOS: ${pedido.productos || 'No especificados'}

🚚 ENVÍO:
• Cajas: ${pedido.cajas_finales || pedido.cajas_estimadas || 'Sin configurar'}
• Método: ${pedido.metodo_envio || 'Sin configurar'}
• Guía: ${pedido.numero_guia || 'Sin asignar'}

📊 ESTADO: ${pedido.estado || 'Por Procesar'}
🕐 CREADO: ${fechaCreacion}`;
    
    alert(detalle);
}

// REEMPLAZA tu función abrirModalConfiguracion() con esta versión completa:

function abrirModalConfiguracion(idPedido) {
    const pedido = pedidos.find(p => p.id === idPedido);
    if (!pedido) {
        alert('❌ Pedido no encontrado');
        return;
    }

    pedidoActualModal = pedido;
    
    // Llenar información del modal
    document.getElementById('modalPedidoId').textContent = pedido.id;
    
    // Información del cliente
    const infoCliente = document.getElementById('infoClienteModal');
    infoCliente.innerHTML = `
        <div><strong>Cliente:</strong> ${pedido.cliente || 'No especificado'}</div>
        <div><strong>Teléfono:</strong> ${pedido.telefono || 'No especificado'}</div>
        <div><strong>Dirección:</strong> ${pedido.direccion || 'No especificada'}</div>
        <div><strong>Productos:</strong> ${pedido.productos || 'No especificados'}</div>
    `;
    
    // Cajas
    document.getElementById('cajasEstimadasDisplay').textContent = pedido.cajas_estimadas || '1';
    document.getElementById('cajasFinales').value = pedido.cajas_finales || pedido.cajas_estimadas || 1;
    
    // Método de envío
    document.getElementById('metodoEstimadoDisplay').textContent = pedido.metodo_envio || 'No especificado';
    
    // Limpiar selección de método
    document.querySelectorAll('input[name="metodoEnvioFinal"]').forEach(radio => {
        radio.checked = false;
    });
    document.querySelectorAll('.envio-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Seleccionar método actual si existe
    if (pedido.metodo_envio) {
        seleccionarEnvio(pedido.metodo_envio);
    }
    
    // Información de seguimiento
    document.getElementById('numeroGuia').value = pedido.numero_guia || '';
    document.getElementById('observacionesEnvio').value = pedido.observaciones_envio || '';
    
    // Mostrar modal
    document.getElementById('modalEnvio').style.display = 'flex';
}

function seleccionarEnvio(metodo) {
    // Limpiar selecciones
    document.querySelectorAll('.envio-option').forEach(option => {
        option.classList.remove('selected');
    });
    document.querySelectorAll('input[name="metodoEnvioFinal"]').forEach(radio => {
        radio.checked = false;
    });
    
    // Seleccionar nuevo método
    const option = document.querySelector(`input[value="${metodo}"]`);
    if (option) {
        option.checked = true;
        option.closest('.envio-option').classList.add('selected');
    }
}



async function confirmarConfiguracion() {
    if (!pedidoActualModal) {
        alert('❌ Error: No hay pedido seleccionado');
        return;
    }
    
    const metodoSeleccionado = document.querySelector('input[name="metodoEnvioFinal"]:checked');
    if (!metodoSeleccionado) {
        alert('❌ Debe seleccionar un método de envío');
        return;
    }
    
    const cajasFinales = parseInt(document.getElementById('cajasFinales').value) || 1;
    const numeroGuia = document.getElementById('numeroGuia').value.trim();
    const observaciones = document.getElementById('observacionesEnvio').value.trim();
    
    if (cajasFinales < 1 || cajasFinales > 50) {
        alert('❌ El número de cajas debe estar entre 1 y 50');
        return;
    }
    
    const confirmMessage = `¿Confirmar configuración de envío?\n\n` +
                          `Pedido: #${pedidoActualModal.id}\n` +
                          `Cliente: ${pedidoActualModal.cliente}\n` +
                          `Método: ${metodoSeleccionado.value}\n` +
                          `Cajas: ${cajasFinales}\n` +
                          `Guía: ${numeroGuia || 'Sin asignar'}\n\n` +
                          `El pedido cambiará a estado "Preparando"`;
    
    if (!confirm(confirmMessage)) return;
    
    const btnConfirmar = document.querySelector('#modalEnvio .btn-success');
    const textoOriginal = btnConfirmar.innerHTML;
    btnConfirmar.innerHTML = '⚙️ Configurando...';
    btnConfirmar.disabled = true;
    
    try {
        const response = await fetch(`/api/bodega/pedidos/${pedidoActualModal.id}/configurar-envio`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                metodo_envio: metodoSeleccionado.value,
                cajas_finales: cajasFinales,
                numero_guia: numeroGuia,
                observaciones_envio: observaciones,
                estado: 'Preparando'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await cargarPedidosDesdeVentas();
            cerrarModal();
            mostrarNotificacion(`✅ Envío configurado correctamente para pedido #${pedidoActualModal.id}`, 'success');
        } else {
            throw new Error(result.message || 'Error al configurar el envío');
        }
        
    } catch (error) {
        console.error('Error configurando envío:', error);
        alert(`❌ Error configurando envío:\n${error.message}`);
    } finally {
        btnConfirmar.innerHTML = textoOriginal;
        btnConfirmar.disabled = false;
    }
}

// REEMPLAZA tus funciones de cerrar modal con estas versiones corregidas:

function cerrarModal() {
    console.log('🔒 Cerrando modal...');
    
    // Buscar el modal por diferentes IDs posibles
    const modal = document.getElementById('modalEnvio') || 
                  document.getElementById('modalConfiguracion') ||
                  document.querySelector('.modal');
    
    if (modal) {
        modal.style.display = 'none';
        console.log('✅ Modal cerrado');
    } else {
        console.log('❌ Modal no encontrado');
        // Buscar todos los elementos con clase modal
        const modales = document.querySelectorAll('.modal, [id*="modal"]');
        modales.forEach((m, index) => {
            console.log(`Modal ${index}:`, m.id, m.style.display);
            m.style.display = 'none';
        });
    }
    
    // Limpiar variable global
    window.pedidoActualModal = null;
    
    // Limpiar body overflow si estaba bloqueado
    document.body.style.overflow = '';
}

// Función alternativa más agresiva
function forzarCerrarModal() {
    console.log('🔒 Forzando cierre de modal...');
    
    // Buscar TODOS los modales posibles
    const selectores = [
        '#modalEnvio',
        '#modalConfiguracion', 
        '.modal',
        '[class*="modal"]',
        '[id*="modal"]'
    ];
    
    selectores.forEach(selector => {
        const elementos = document.querySelectorAll(selector);
        elementos.forEach(elemento => {
            if (elemento.style.display !== 'none') {
                console.log(`Cerrando elemento:`, selector, elemento);
                elemento.style.display = 'none';
                elemento.style.visibility = 'hidden';
                elemento.style.opacity = '0';
            }
        });
    });
    
    // Limpiar variables globales
    window.pedidoActualModal = null;
    
    // Limpiar estilos del body
    document.body.style.overflow = '';
    document.body.classList.remove('modal-open');
    
    console.log('✅ Cierre forzado completado');
}

// Mejorar la función de configurar eventos para incluir cierre con ESC
function configurarEventosModal() {
    // Cerrar con ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            console.log('ESC presionado - cerrando modal');
            forzarCerrarModal();
        }
    });
    
    // Cerrar al hacer click en el fondo
    document.addEventListener('click', function(event) {
        // Verificar si el click fue en el fondo del modal
        if (event.target.classList.contains('modal')) {
            console.log('Click en fondo del modal - cerrando');
            forzarCerrarModal();
        }
    });
    
    console.log('✅ Eventos de modal configurados');
}

// Llamar esta función al inicializar
configurarEventosModal();

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
        e.preventDefault();
        actualizarDatos();
    }
    
    if (e.ctrlKey && e.key === 'a' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        document.getElementById('selectAll')?.click();
    }
});

// ESTILOS CSS PARA NOTIFICACIONES
const estilosNotificaciones = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .connection-status.updating {
        background-color: #fff3cd;
        color: #856404;
        border-color: #ffeaa7;
    }
`;

if (!document.getElementById('estilos-notificaciones')) {
    const style = document.createElement('style');
    style.id = 'estilos-notificaciones';
    style.textContent = estilosNotificaciones;
    document.head.appendChild(style);
}

console.log('✅ Sistema de bodega completamente inicializado - SIN configuración rápida');

// AGREGA esta función al final de tu bodega.js para debuggear:

function diagnosticarFiltros() {
    console.log('🔬 === DIAGNÓSTICO COMPLETO DE FILTROS ===');
    
    // 1. Verificar si hay pedidos cargados
    console.log('1. DATOS:');
    console.log('- Total pedidos:', pedidos ? pedidos.length : 'UNDEFINED');
    console.log('- Pedidos filtrados:', pedidosFiltrados ? pedidosFiltrados.length : 'UNDEFINED');
    
    if (pedidos && pedidos.length > 0) {
        console.log('- Primer pedido ejemplo:', pedidos[0]);
        console.log('- Estados únicos:', [...new Set(pedidos.map(p => p.estado).filter(Boolean))]);
        console.log('- Prioridades únicas:', [...new Set(pedidos.map(p => p.prioridad).filter(Boolean))]);
        console.log('- Métodos únicos:', [...new Set(pedidos.map(p => p.metodo_envio).filter(Boolean))]);
    }
    
    // 2. Verificar elementos HTML
    console.log('\n2. ELEMENTOS HTML:');
    const elementos = {
        searchInput: document.getElementById('searchInput'),
        filterEstado: document.getElementById('filterEstado'),
        filterPrioridad: document.getElementById('filterPrioridad'),
        filterMetodoEnvio: document.getElementById('filterMetodoEnvio')
    };
    
    Object.keys(elementos).forEach(key => {
        const elemento = elementos[key];
        if (elemento) {
            console.log(`- ${key}: ✅ existe, valor: "${elemento.value}"`);
        } else {
            console.log(`- ${key}: ❌ NO EXISTE`);
        }
    });
    
    // 3. Verificar eventos
    console.log('\n3. EVENTOS:');
    const filtroMetodo = document.getElementById('filterMetodoEnvio');
    if (filtroMetodo) {
        console.log('- Eventos en filtro método:', getEventListeners ? getEventListeners(filtroMetodo) : 'No se puede verificar');
    }
    
    // 4. Probar filtro manualmente
    console.log('\n4. PRUEBA MANUAL:');
    if (pedidos && pedidos.length > 0) {
        console.log('Probando filtro de prioridad "Alta"...');
        const resultadoPrueba = pedidos.filter(p => p.prioridad === 'Alta');
        console.log('- Resultado:', resultadoPrueba.length, 'pedidos');
        
        console.log('Probando búsqueda "maritza"...');
        const resultadoBusqueda = pedidos.filter(p => 
            p.cliente && p.cliente.toLowerCase().includes('maritza')
        );
        console.log('- Resultado:', resultadoBusqueda.length, 'pedidos');
    }
    
    console.log('\n🔬 === FIN DIAGNÓSTICO ===');
}

// AGREGA esta función de diagnóstico al final de tu bodega.js:

function diagnosticoLimpieza() {
    console.log('🔬 === DIAGNÓSTICO COMPLETO DE LIMPIEZA ===');
    
    // 1. Verificar datos
    console.log('1. VERIFICACIÓN DE DATOS:');
    console.log('- window.pedidos existe:', !!window.pedidos);
    console.log('- Cantidad de pedidos:', window.pedidos ? window.pedidos.length : 0);
    
    if (window.pedidos && window.pedidos.length > 0) {
        console.log('- Primer pedido:', window.pedidos[0]);
        
        // Mostrar TODOS los estados exactos
        console.log('\n2. ESTADOS DE TODOS LOS PEDIDOS:');
        window.pedidos.forEach((p, index) => {
            console.log(`Pedido ${index + 1}:`);
            console.log(`  ID: ${p.id}`);
            console.log(`  Cliente: ${p.cliente}`);
            console.log(`  Estado: "${p.estado}" (caracteres: ${p.estado ? p.estado.length : 0})`);
            console.log(`  Tipo: ${typeof p.estado}`);
            console.log('  ---');
        });
        
        // Buscar entregados con diferentes métodos
        console.log('\n3. BÚSQUEDA DE ENTREGADOS:');
        
        // Método 1: Búsqueda exacta
        const entregados1 = window.pedidos.filter(p => p.estado === 'ENTREGADO');
        console.log('- Método 1 (ENTREGADO exacto):', entregados1.length);
        
        // Método 2: Búsqueda insensible a mayúsculas
        const entregados2 = window.pedidos.filter(p => 
            String(p.estado || '').toLowerCase() === 'entregado'
        );
        console.log('- Método 2 (insensible a mayúsculas):', entregados2.length);
        
        // Método 3: Búsqueda con includes
        const entregados3 = window.pedidos.filter(p => 
            String(p.estado || '').toLowerCase().includes('entregado')
        );
        console.log('- Método 3 (contains entregado):', entregados3.length);
        
        // Método 4: Buscar todas las variaciones visuales
        const entregados4 = window.pedidos.filter(p => {
            const estado = String(p.estado || '').trim();
            return estado === 'ENTREGADO' || 
                   estado === 'Entregado' || 
                   estado === 'entregado' ||
                   estado === 'DELIVERED' ||
                   estado === 'Delivered' ||
                   estado === 'delivered';
        });
        console.log('- Método 4 (múltiples variaciones):', entregados4.length);
        
        if (entregados4.length > 0) {
            console.log('- Entregados encontrados:', entregados4.map(p => `#${p.id} (${p.estado})`));
        }
    }
    
    // 4. Verificar ruta
    console.log('\n4. VERIFICACIÓN DE RUTA:');
    const url = '/api/bodega/limpiar-entregados';
    console.log('- URL que se llamará:', url);
    
    // Test de conectividad
    fetch(url, {
        method: 'OPTIONS',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        console.log('- Test de conectividad:', response.status, response.statusText);
    })
    .catch(error => {
        console.log('- Error de conectividad:', error.message);
    });
    
    console.log('\n🔬 === FIN DIAGNÓSTICO ===');
}

// FUNCIÓN DE LIMPIEZA SIMPLIFICADA PARA TESTING
function limpiarEntregadosSimple() {
    console.log('🧪 === LIMPIEZA SIMPLIFICADA ===');
    
    if (!window.pedidos || window.pedidos.length === 0) {
        alert('No hay pedidos cargados');
        return;
    }
    
    // Mostrar todos los estados
    const estados = window.pedidos.map(p => `#${p.id}: "${p.estado}"`);
    console.log('Estados encontrados:', estados);
    
    // Buscar entregados de manera muy amplia
    const entregados = window.pedidos.filter(p => {
        const estado = String(p.estado || '').toLowerCase().trim();
        return estado.includes('entregado') || 
               estado.includes('delivered') ||
               estado === 'entregado' ||
               estado === 'ENTREGADO';
    });
    
    console.log(`Encontrados ${entregados.length} pedidos entregados`);
    
    if (entregados.length === 0) {
        alert(`No se encontraron pedidos entregados.\n\nEstados en BD:\n${window.pedidos.map(p => p.estado).join('\n')}`);
        return;
    }
    
    const detalles = entregados.map(p => `#${p.id} - ${p.cliente} (${p.estado})`).join('\n');
    
    if (confirm(`¿Eliminar ${entregados.length} pedidos?\n\n${detalles}`)) {
        console.log('Usuario confirmó eliminación');
        
        // Hacer la llamada real al servidor
        fetch('/api/bodega/limpiar-entregados', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                pedidos_ids: entregados.map(p => p.id),
                confirmar_eliminacion: true,
                debug_info: {
                    total_pedidos: window.pedidos.length,
                    entregados_encontrados: entregados.length,
                    estados_originales: entregados.map(p => ({id: p.id, estado: p.estado}))
                }
            })
        })
        .then(response => {
            console.log('Respuesta del servidor:', response.status, response.statusText);
            return response.text();
        })
        .then(text => {
            console.log('Contenido de respuesta:', text);
            try {
                const json = JSON.parse(text);
                console.log('JSON parseado:', json);
                if (json.success) {
                    alert(`✅ ${json.eliminados || entregados.length} pedidos eliminados`);
                    cargarPedidosDesdeVentas();
                } else {
                    alert(`❌ Error: ${json.message}`);
                }
            } catch (e) {
                console.log('No es JSON válido, respuesta raw:', text);
                alert(`❌ Respuesta inesperada del servidor: ${text.substring(0, 200)}`);
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
            alert(`❌ Error de conexión: ${error.message}`);
        });
    }
}