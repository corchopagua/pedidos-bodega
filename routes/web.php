<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PedidoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('ventas.form');
});

/*
|--------------------------------------------------------------------------
| RUTAS DE VENTAS
|--------------------------------------------------------------------------
*/

// Formulario de ventas (vista principal)
Route::get('/ventas', [PedidoController::class, 'formularioPedido'])->name('ventas.form');

// Enviar nuevo pedido desde ventas
Route::post('/ventas/enviar', [PedidoController::class, 'enviarPedido'])->name('ventas.enviar');



// Validar disponibilidad de número de pedido
Route::get('/api/ventas/validar-numero/{numero}', [PedidoController::class, 'validarNumeroPedido'])->name('api.ventas.validar-numero');

/*
|--------------------------------------------------------------------------
| RUTAS DE BODEGA
|--------------------------------------------------------------------------
*/

// Panel de bodega (vista principal)
Route::get('/bodega', [PedidoController::class, 'index'])->name('bodega.index');

// API para obtener todos los pedidos (bodega)
Route::get('/api/bodega/pedidos', [PedidoController::class, 'obtenerPedidos'])->name('api.bodega.pedidos');

// Ver detalle completo de un pedido
Route::get('/api/bodega/pedidos/{id}/detalle', [PedidoController::class, 'verDetalle'])->name('api.bodega.pedido.detalle');

/*
|--------------------------------------------------------------------------
| GESTIÓN DE ESTADOS
|--------------------------------------------------------------------------
*/

// Cambiar estado general de un pedido (API)
Route::patch('/api/bodega/pedidos/{id}/estado', [PedidoController::class, 'cambiarEstado'])->name('api.bodega.cambiar-estado');

// Procesar pedido (Por Procesar → Preparando) - Método tradicional
Route::patch('/bodega/pedidos/{id}/procesar', [PedidoController::class, 'procesarPedido'])->name('bodega.procesar');

// Marcar como entregado (Preparando → Entregado) - Método tradicional
Route::patch('/bodega/pedidos/{id}/entregar', [PedidoController::class, 'marcarEntregado'])->name('bodega.entregar');

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE ENVÍOS
|--------------------------------------------------------------------------
*/

// Configurar envío individual (modal principal)
Route::patch('/api/bodega/pedidos/{id}/configurar-envio', [PedidoController::class, 'configurarEnvio'])->name('api.bodega.configurar-envio');

// Configuración rápida por método de envío
Route::post('/api/bodega/configuracion-rapida', [PedidoController::class, 'configuracionRapida'])->name('api.bodega.configuracion-rapida');

// Obtener pedidos disponibles para configuración rápida por método
Route::get('/api/bodega/pedidos-disponibles/{metodo}', [PedidoController::class, 'pedidosDisponiblesPorMetodo'])->name('api.bodega.pedidos-disponibles');

// Ajustar cajas masivamente
Route::patch('/api/bodega/ajustar-cajas-masivo', [PedidoController::class, 'ajustarCajasMasivo'])->name('api.bodega.ajustar-cajas-masivo');

/*
|--------------------------------------------------------------------------
| BÚSQUEDA Y FILTROS
|--------------------------------------------------------------------------
*/

// Buscar pedidos con filtros
Route::get('/api/bodega/buscar', [PedidoController::class, 'buscar'])->name('api.bodega.buscar');

// Filtrar por estado
Route::get('/api/bodega/filtrar/estado/{estado}', [PedidoController::class, 'filtrarPorEstado'])->name('api.bodega.filtrar.estado');

// Filtrar por método de envío
Route::get('/api/bodega/filtrar/metodo/{metodo}', [PedidoController::class, 'filtrarPorMetodo'])->name('api.bodega.filtrar.metodo');

// Filtrar por prioridad
Route::get('/api/bodega/filtrar/prioridad/{prioridad}', [PedidoController::class, 'filtrarPrioridad'])->name('api.bodega.filtrar.prioridad');

/*
|--------------------------------------------------------------------------
| GESTIÓN MASIVA
|--------------------------------------------------------------------------
*/

// Procesar múltiples pedidos pendientes
Route::post('/api/bodega/procesar-pendientes', [PedidoController::class, 'procesarPendientes'])->name('api.bodega.procesar-pendientes');

// Eliminar todos los pedidos entregados
Route::delete('/api/bodega/limpiar-entregados', [PedidoController::class, 'eliminarEntregados']);

// Eliminar pedido específico
Route::delete('/api/bodega/pedidos/{id}', [PedidoController::class, 'eliminarPedido'])->name('api.bodega.eliminar-pedido');

// Selección masiva por criterios
Route::post('/api/bodega/seleccion-masiva', [PedidoController::class, 'seleccionMasiva'])->name('api.bodega.seleccion-masiva');

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS Y REPORTES
|--------------------------------------------------------------------------
*/

// Estadísticas generales para dashboard
Route::get('/api/bodega/estadisticas', [PedidoController::class, 'estadisticasGenerales'])->name('api.bodega.estadisticas');

// Estadísticas por método de envío
Route::get('/api/bodega/estadisticas/envio', [PedidoController::class, 'estadisticasPorEnvio'])->name('api.bodega.estadisticas.envio');

// Reporte detallado
Route::get('/api/bodega/reporte-detallado', [PedidoController::class, 'reporteDetallado'])->name('api.bodega.reporte-detallado');

// Obtener último ID para notificaciones en tiempo real
Route::get('/api/ultimo-id', [PedidoController::class, 'ultimoId'])->name('api.ultimo-id');

Route::get('/api/ventas/estadisticas', [PedidoController::class, 'estadisticasVentas'])->name('api.ventas.estadisticas');

/*
|--------------------------------------------------------------------------
| EXPORTACIÓN
|--------------------------------------------------------------------------
*/

// Exportar a Excel/CSV
Route::get('/bodega/exportar', [PedidoController::class, 'exportar'])->name('bodega.exportar');

// Exportar reporte por fechas
Route::get('/bodega/exportar-rango', [PedidoController::class, 'exportarPorRango'])->name('bodega.exportar-rango');

// Exportar por método de envío
Route::get('/bodega/exportar-metodo/{metodo}', [PedidoController::class, 'exportarPorMetodo'])->name('bodega.exportar-metodo');

/*
|--------------------------------------------------------------------------
| RUTAS DE SEGUIMIENTO
|--------------------------------------------------------------------------
*/

// Actualizar número de guía
Route::patch('/api/bodega/pedidos/{id}/numero-guia', [PedidoController::class, 'actualizarNumeroGuia'])->name('api.bodega.actualizar-guia');

// Actualizar observaciones de envío
Route::patch('/api/bodega/pedidos/{id}/observaciones', [PedidoController::class, 'actualizarObservaciones'])->name('api.bodega.actualizar-observaciones');

// Consultar estado de envío (integración futura con APIs de paqueterías)
Route::get('/api/seguimiento/{numeroGuia}', [PedidoController::class, 'consultarSeguimiento'])->name('api.seguimiento');

/*
|--------------------------------------------------------------------------
| NOTIFICACIONES Y TIEMPO REAL
|--------------------------------------------------------------------------
*/

// Verificar nuevos pedidos para alertas
Route::get('/api/verificar-nuevos-pedidos', [PedidoController::class, 'verificarNuevosPedidos'])->name('api.verificar-nuevos');

// Marcar notificación como vista
Route::post('/api/notificacion-vista/{id}', [PedidoController::class, 'marcarNotificacionVista'])->name('api.notificacion-vista');

/*
|--------------------------------------------------------------------------
| RUTAS DE UTILIDAD
|--------------------------------------------------------------------------
*/

// Obtener opciones para selects dinámicos
Route::get('/api/opciones/metodos-envio', function() {
    return response()->json(\App\Models\Pedido::METODOS_ENVIO);
});

Route::get('/api/opciones/prioridades', function() {
    return response()->json(\App\Models\Pedido::PRIORIDADES);
});

Route::get('/api/opciones/estados', function() {
    return response()->json(\App\Models\Pedido::ESTADOS);
});

Route::get('/api/opciones/formas-pago', function() {
    return response()->json(\App\Models\Pedido::FORMAS_PAGO);
});

Route::delete('/api/bodega/limpiar-entregados', [PedidoController::class, 'eliminarEntregados']);
Route::post('/api/bodega/limpiar-entregados', [PedidoController::class, 'eliminarEntregados']);
