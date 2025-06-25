<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Bodega - Gestión de Entregas</title>
    
    <link rel="stylesheet" href="{{ asset('css/bodega.css') }}">
</head>
<body>
    <div class="connection-status connected" id="connectionStatus">
        ✓ Sistema Activo
    </div>
    
    <div class="new-order-alert" id="newOrderAlert">
        🔔 ¡Nuevo pedido recibido! #<span id="newOrderId"></span>
    </div>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>📦 Panel de Bodega</h1>
            <p>Gestión y seguimiento de entregas en tiempo real</p>
            <div class="time-display" id="currentTime"></div>
            
            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="number" id="quickTotal">0</div>
                    <div class="label">Total Hoy</div>
                </div>
                <div class="quick-stat">
                    <div class="number" id="quickPendientes">0</div>
                    <div class="label">Pendientes</div>
                </div>
                <div class="quick-stat">
                    <div class="number" id="quickCompletados">0</div>
                    <div class="label">Completados</div>
                </div>
                <div class="quick-stat">
                    <div class="number" id="quickCajasDespachadas">0</div>
                    <div class="label">Cajas Despachadas</div>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="stat-icon">👜</div>
                <div class="stat-number" id="statProcesar">0</div>
                <div class="stat-label">Por Procesar</div>
                <div class="stat-sublabel">Requieren configuración</div>
            </div>
            <div class="stat-card cyan">
                <div class="stat-icon">⚙️</div>
                <div class="stat-number" id="statPreparando">0</div>
                <div class="stat-label">Preparando</div>
                <div class="stat-sublabel">Listos para envío</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">🚚</div>
                <div class="stat-number" id="statEntregados">0</div>
                <div class="stat-label">Entregados</div>
                <div class="stat-sublabel">Completados hoy</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon">📊</div>
                <div class="stat-number" id="statEficiencia">0%</div>
                <div class="stat-label">Eficiencia</div>
                <div class="stat-sublabel">Completados/Total</div>
            </div>
        </div>

        <!-- Panel de configuración rápida -->
        <div class="quick-config-panel">
            <div class="panel-header">
                <h3>⚡ Configuración Rápida de Envíos</h3>
                <p>Configura múltiples pedidos con el mismo método de envío</p>
            </div>
            
            
                
               
                </div>
            </div>
        </div>
        
        <div class="controls">
            <button class="btn btn-primary" onclick="actualizarDatos()">
                🔄 Actualizar Datos
            </button>
            <button class="btn btn-warning" onclick="procesarPendientes()">
                ⚡ Procesar Pendientes
            </button>
            <button class="btn btn-danger" onclick="limpiarEntregados()">
                🗑️ Limpiar Entregados
            </button>
            <button class="btn btn-secondary" onclick="generarReporte()">
                📄 Reporte Detallado
            </button>
            <button class="btn btn-secondary" onclick="exportarExcel()">
                📊 Exportar Excel
            </button>
        </div>
        
        
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    📋 Gestión de Pedidos - <span id="pedidosCount">0</span> pedidos
                </div>
                <div class="table-legend">
                    <span class="legend-item">🟡 Por Procesar</span>
                    <span class="legend-item">🔵 Preparando</span>
                    <span class="legend-item">🟢 Entregado</span>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="pedidos-table">
                    <thead>
                        <tr>
                            <th style="width: 8%;">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                ID
                            </th>
                            <th style="width: 18%;">Cliente & Contacto</th>
                            <th style="width: 20%;">Dirección & Entrega</th>
                            <th style="width: 12%;">Pago & Monto</th>
                            <th style="width: 15%;">Productos</th>
                            <th style="width: 15%;">📦 Cajas & 🚚 Envío</th>
                            <th style="width: 8%;">Estado</th>
                            <th style="width: 14%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaPedidos">
                        <tr class="loading-row">
                            <td colspan="8" class="loading">
                                <div class="loading-content">
                                    <div class="loading-spinner">🔄</div>
                                    <div class="loading-text">Cargando pedidos desde sistema de ventas...</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal principal para configurar envío individual -->
    <div id="modalEnvio" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>📦 Configurar Envío - Pedido #<span id="modalPedidoId"></span></h3>
                <span class="modal-close" onclick="cerrarModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Información del cliente -->
                <div class="info-section">
                    <h4>👤 Información del Cliente</h4>
                    <div id="infoClienteModal" class="cliente-info-grid">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>

                <!-- Configuración de cajas -->
                <div class="config-section">
                    <h4>📦 Configuración de Cajas</h4>
                    <div class="cajas-config">
                        <div class="cajas-estimadas">
                            <label>Cajas Estimadas por Ventas:</label>
                            <span id="cajasEstimadasDisplay" class="cajas-display">-</span>
                        </div>
                        <div class="cajas-finales">
                            <label>Cajas Finales a Despachar:</label>
                            <div class="cajas-input-group">
                               
                                <input type="number" id="cajasFinales" min="1" max="50" value="1" class="cajas-input-main">
                                
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Método de envío -->
                <div class="config-section">
                    <h4>🚚 Método de Envío</h4>
                    <div class="envio-method-info">
                        <div class="metodo-estimado">
                            <label>Método Solicitado por Ventas:</label>
                            <span id="metodoEstimadoDisplay" class="metodo-display">-</span>
                        </div>
                        <div class="metodo-final">
                            <label>Método Final de Envío:</label>
                            <div class="envio-options-bodega">
                                <div class="envio-option" onclick="seleccionarEnvio('Guatex')">
                                    <div class="envio-icon">📮</div>
                                    <div class="envio-details">
                                        <div class="envio-name">Guatex</div>
                                        <div class="envio-desc">Paquetería nacional</div>
                                    </div>
                                    <input type="radio" name="metodoEnvioFinal" value="Guatex" id="envioGuatex">
                                </div>
                                <div class="envio-option" onclick="seleccionarEnvio('Cargo Expreso')">
                                    <div class="envio-icon">🚛</div>
                                    <div class="envio-details">
                                        <div class="envio-name">Cargo Expreso</div>
                                        <div class="envio-desc">Envío rápido</div>
                                    </div>
                                    <input type="radio" name="metodoEnvioFinal" value="Cargo Expreso" id="envioCargoExpreso">
                                </div>
                                <div class="envio-option" onclick="seleccionarEnvio('Entrega Local')">
                                    <div class="envio-icon">🏠</div>
                                    <div class="envio-details">
                                        <div class="envio-name">Entrega Local</div>
                                        <div class="envio-desc">Entrega directa</div>
                                    </div>
                                    <input type="radio" name="metodoEnvioFinal" value="Entrega Local" id="envioLocal">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de seguimiento -->
                <div class="config-section">
                    <h4>📄 Información de Seguimiento</h4>
                    <div class="form-group">
                        <label for="numeroGuia">Número de Guía de Envío:</label>
                        <input type="text" id="numeroGuia" placeholder="Ej: GT123456789 o CE987654321" class="form-input">
                        <small class="form-hint">Número de seguimiento proporcionado por la paquetería</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacionesEnvio">Observaciones del Envío:</label>
                        <textarea id="observacionesEnvio" rows="3" placeholder="Instrucciones especiales, horarios de entrega, comentarios..." class="form-textarea"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModal()">❌ Cancelar</button>
                <button class="btn btn-success" onclick="confirmarConfiguracion()">✅ Guardar y Preparar Pedido</button>
            </div>
        </div>
    </div>

    <!-- Modal para configuración rápida -->
    <div id="modalConfigRapida" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⚡ Configuración Rápida - <span id="metodoRapidoTitle"></span></h3>
                <span class="modal-close" onclick="cerrarModalRapido()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="config-rapida-info">
                    <p>Configura múltiples pedidos con <strong id="metodoRapidoNombre"></strong>:</p>
                </div>
                
                <div class="pedidos-rapidos-container">
                    <div class="pedidos-header">
                        <input type="checkbox" id="selectAllRapido" onchange="toggleSelectAllRapido()">
                        <label for="selectAllRapido">Seleccionar todos</label>
                    </div>
                    <div id="listaPedidosRapidos" class="pedidos-rapidos-list">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>
                
                <div class="config-global">
                    <div class="form-group">
                        <label>📦 Cajas por defecto:</label>
                        <div class="cajas-rapidas-group">
                            <button type="button" onclick="ajustarCajasRapidas(-1)">-</button>
                            <input type="number" id="cajasRapidas" value="1" min="1" max="50">
                            <button type="button" onclick="ajustarCajasRapidas(1)">+</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>📄 Prefijo de guía (opcional):</label>
                        <input type="text" id="prefijoGuiaRapido" placeholder="Ej: GT, CE, LOCAL">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModalRapido()">Cancelar</button>
                <button class="btn btn-primary" onclick="aplicarConfiguracionRapida()">✅ Aplicar a Seleccionados</button>
            </div>
        </div>
    </div>

    

    <script src="{{ asset('js/bodega.js') }}"></script>
</body>
</html>