<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas - Registro de Pedidos</title>
    <link rel="stylesheet" href="{{ asset('css/ventas.css') }}">
</head>
<body>
    <div class="connection-status connected" id="connectionStatus">
        ✓ Conectado a Sistema
    </div>
    
    <div class="container">
        <div class="header">
            <h1>📝 Sistema de Ventas</h1>
            <p>Registro y Gestión de Pedidos</p>
        </div>
        
        <div class="form-container">
            <div id="successMessage" class="success-message">
                ✅ Pedido #<span id="pedidoNumero"></span> enviado exitosamente a bodega
                <br><small>El equipo de bodega configurará el envío y las cajas</small>
            </div>
            
            <form id="pedidoForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>👤 Nombre del Cliente <span class="required">*</span></label>
                        <input type="text" id="nombreCliente" required placeholder="Ingrese el nombre completo">
                    </div>
                    <div class="form-group">
                        <label>📞 Teléfono</label>
                        <input type="tel" id="telefono" placeholder="Número de contacto">
                        <div class="form-hint">Para confirmación de entrega</div>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>📍 Dirección de Entrega <span class="required">*</span></label>
                        <textarea id="direccion" required placeholder="Dirección completa de entrega con referencias"></textarea>
                        <div class="form-hint">Incluya zona, referencias y puntos conocidos</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>💳 Forma de Pago <span class="required">*</span></label>
                        <select id="formaPago" required>
                            <option value="">Seleccionar método...</option>
                            <option value="Credito">📦 Crédito</option>
                            <option value="Por Cobrar">📝 Por Cobrar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>⚠️ Prioridad</label>
                        <select id="prioridad">
                            <option value="Baja">🟢 Baja</option>
                            <option value="Media" selected>🟡 Media</option>
                            <option value="Alta">🔴 Alta</option>
                            <option value="Urgente">🚨 Urgente</option>
                        </select>
                        <div class="form-hint">Seleccione según la urgencia del cliente</div>
                    </div>
                </div>

                <!-- NUEVA SECCIÓN: Configuración de Envío -->
                <div class="form-section envio-section">
                    <h3>🚚 Configuración de Envío</h3>
                    <p class="section-description">Seleccione el método de envío y especifique las cajas estimadas</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>📦 Método de Envío <span class="required">*</span></label>
                            <div class="envio-options">
                                <div class="envio-card" data-method="Guatex">
                                    <div class="envio-icon">📮</div>
                                    <div class="envio-name">Guatex</div>
                                    <div class="envio-desc">Paquetería nacional</div>
                                    <input type="radio" name="metodoEnvio" value="Guatex" id="radioGuatex">
                                </div>
                                <div class="envio-card" data-method="Cargo Expreso">
                                    <div class="envio-icon">🚛</div>
                                    <div class="envio-name">Cargo Expreso</div>
                                    <div class="envio-desc">Envío rápido</div>
                                    <input type="radio" name="metodoEnvio" value="Cargo Expreso" id="radioCargoExpreso">
                                </div>
                                <div class="envio-card" data-method="Entrega Local">
                                    <div class="envio-icon">🏠</div>
                                    <div class="envio-name">Entrega Local</div>
                                    <div class="envio-desc">Entrega directa</div>
                                    <input type="radio" name="metodoEnvio" value="Entrega Local" id="radioEntregaLocal">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📦 Cajas Estimadas</label>
                            <div class="cajas-estimadas-group">
                                <button type="button" class="btn-cajas-menos" onclick="cambiarCajasEstimadas(-1)">-</button>
                                <input type="number" id="cajasEstimadas" value="1" min="1" max="50" class="cajas-input">
                                <button type="button" class="btn-cajas-mas" onclick="cambiarCajasEstimadas(1)">+</button>
                            </div>
                            <div class="form-hint">Estimación inicial (bodega confirmará cantidad final)</div>
                        </div>
                        <div class="form-group">
                            <label>📅 Fecha de Entrega Deseada</label>
                            <input type="date" id="fechaEntrega">
                            <div class="form-hint">Dejar vacío para entrega inmediata</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>💰 Monto Estimado</label>
                            <input type="number" id="montoTotal" step="0.01" placeholder="0.00">
                            <div class="form-hint">Monto en quetzales (GTQ)</div>
                        </div>
                        <div class="form-group">
                            <label>📄 Número de Guía (opcional)</label>
                            <input type="text" id="numeroGuiaPreview" placeholder="Se asignará en bodega" readonly>
                            <div class="form-hint">Bodega asignará el número de seguimiento</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>📋 Detalle del Pedido <span class="required">*</span></label>
                        <textarea id="detallePedido" required placeholder="Ejemplo:&#10;- 2 Cajas de producto A (SKU: ABC123)&#10;- 1 Unidad de producto B (SKU: DEF456)&#10;- 3 Paquetes de producto C (SKU: GHI789)"></textarea>
                        <div class="form-hint">Liste todos los productos con cantidades exactas</div>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>💬 Información Adicional</label>
                        <textarea id="informacionAdicional" placeholder="Instrucciones especiales para entrega:&#10;- Horario preferido&#10;- Instrucciones de acceso&#10;- Comentarios especiales&#10;- Observaciones sobre el envío"></textarea>
                        <div class="form-hint">Cualquier detalle importante para la entrega</div>
                    </div>
                </div>

                <!-- Resumen del pedido -->
                <div class="resumen-pedido">
                    <h4>📋 Resumen del Pedido</h4>
                    <div class="resumen-content">
                        <div class="resumen-item">
                            <span class="resumen-label">Cliente:</span>
                            <span class="resumen-value" id="resumenCliente">-</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-label">Método de Envío:</span>
                            <span class="resumen-value" id="resumenEnvio">-</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-label">Cajas Estimadas:</span>
                            <span class="resumen-value" id="resumenCajas">1</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-label">Prioridad:</span>
                            <span class="resumen-value" id="resumenPrioridad">Media</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">
                        🗑️ Limpiar Formulario
                    </button>
                    <button type="submit" class="btn btn-primary">
                        📦 Enviar a Bodega
                    </button>
                </div>
            </form>
        </div>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number" id="pedidosHoy">0</div>
                <div class="stat-label">Pedidos Hoy</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="ventasHoy">Q0</div>
                <div class="stat-label">Ventas Hoy</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="pendientes">0</div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="cajasHoy">0</div>
                <div class="stat-label">Cajas Estimadas</div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/ventas.js') }}"></script>
</body>
</html>