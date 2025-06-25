<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VENTAS - Formulario y registro de pedidos
    |--------------------------------------------------------------------------
    */

    /**
     * Mostrar el formulario de ventas (Sala de Ventas)
     */
    public function formularioPedido()
    {
        return view('ventas.formulario');
    }

    /**
     * Guardar un nuevo pedido (Sala de Ventas)
     */
    public function enviarPedido(Request $request)
    {
        // Log para debug
        Log::info('Pedido recibido en controller:', $request->all());
        
        // Validación usando las constantes correctas del modelo
        $validator = Validator::make($request->all(), [
            'nombreCliente' => 'required|string|max:255|min:2',
            'telefono' => 'nullable|string|max:20|regex:/^[0-9\+\-\s\(\)]+$/',
            'direccion' => 'required|string|min:10|max:500',
            'formaPago' => ['required', 'string', Rule::in(array_keys(Pedido::FORMAS_PAGO))],
            'detallePedido' => 'required|string|min:5',
            'prioridad' => ['nullable', Rule::in(array_keys(Pedido::PRIORIDADES))],
            'metodoEnvio' => ['required', Rule::in(array_keys(Pedido::METODOS_ENVIO))],
            'cajasEstimadas' => 'required|integer|min:1|max:50',
            'fechaEntrega' => 'nullable|date|after_or_equal:today',
            'montoTotal' => 'nullable|numeric|min:0|max:999999.99',
            'informacionAdicional' => 'nullable|string|max:1000'
        ], [
            'nombreCliente.required' => 'El nombre del cliente es obligatorio',
            'nombreCliente.min' => 'El nombre debe tener al menos 2 caracteres',
            'direccion.required' => 'La dirección es obligatoria',
            'direccion.min' => 'La dirección debe ser más específica (mínimo 10 caracteres)',
            'detallePedido.required' => 'Debe especificar los productos del pedido',
            'detallePedido.min' => 'El detalle del pedido debe ser más específico',
            'metodoEnvio.required' => 'Debe seleccionar un método de envío',
            'cajasEstimadas.required' => 'Debe especificar las cajas estimadas',
            'cajasEstimadas.min' => 'Debe ser al menos 1 caja',
            'fechaEntrega.after_or_equal' => 'La fecha de entrega no puede ser anterior a hoy',
            'telefono.regex' => 'El formato del teléfono no es válido'
        ]);

        if ($validator->fails()) {
            Log::error('Errores de validación:', $validator->errors()->toArray());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Generar número de pedido único
            $numeroPedido = Pedido::generarNumeroPedido();

            // Crear pedido con campos que coinciden con el modelo
            $pedido = Pedido::create([
                'numero_pedido' => $numeroPedido,
                'cliente' => trim($request->nombreCliente),
                'telefono' => $request->telefono ? trim($request->telefono) : null,
                'direccion' => trim($request->direccion),
                'forma_pago' => $request->formaPago,
                'prioridad' => $request->prioridad ?? 'Media',
                'fecha_entrega' => $request->fechaEntrega,
                'monto_total' => $request->montoTotal ?? 0,
                'productos' => trim($request->detallePedido),
                'info_adicional' => $request->informacionAdicional ? trim($request->informacionAdicional) : null,
                'estado' => 'Por Procesar',
                'metodo_envio' => $request->metodoEnvio,
                'cajas_estimadas' => $request->cajasEstimadas,
                'fecha_creacion_local' => now()->format('d/m/Y H:i:s'),
                'usuario_creacion' => auth()->id() ?? null
            ]);

            DB::commit();

            Log::info("Nuevo pedido creado: #{$pedido->id} - {$pedido->cliente} - {$pedido->metodo_envio}");

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => '✅ Pedido enviado a bodega exitosamente',
                    'pedido_id' => $pedido->id,
                    'numero_pedido' => $numeroPedido,
                    'pedido' => $pedido->toApiArray()
                ]);
            }

            return redirect()->route('ventas.form')
                ->with('success', "✅ Pedido #{$numeroPedido} enviado a bodega exitosamente");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear pedido: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el pedido: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Error al procesar el pedido. Intente nuevamente.')->withInput();
        }
    }

    /**
     * Obtener estadísticas para la vista de ventas
     */
    public function estadisticasVentas(): JsonResponse
    {
        $estadisticas = Pedido::estadisticasHoy();

        return response()->json([
            'success' => true,
            'estadisticas' => [
                'pedidos_hoy' => $estadisticas['pedidos_hoy'],
                'ventas_hoy' => number_format($estadisticas['ventas_hoy'], 2),
                'pendientes' => $estadisticas['pendientes'],
                'cajas_hoy' => $estadisticas['cajas_estimadas_hoy']
            ]
        ]);
    }

    /**
     * Validar disponibilidad de número de pedido
     */
    public function validarNumeroPedido($numero): JsonResponse
    {
        $existe = Pedido::where('numero_pedido', $numero)->exists();

        return response()->json([
            'success' => true,
            'disponible' => !$existe
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BODEGA - Panel principal y gestión
    |--------------------------------------------------------------------------
    */

    /**
     * Mostrar la vista de bodega (Panel de Gestión)
     */
    public function index()
    {
        $pedidos = Pedido::with(['usuarioCreador', 'usuarioProcesamiento'])
                         ->orderBy('created_at', 'desc')
                         ->get();
        
        $estadisticas = Pedido::estadisticasGenerales();
        $estadisticasHoy = Pedido::estadisticasHoy();
        
        // Calcular eficiencia
        $estadisticas['eficiencia'] = $estadisticas['total'] > 0 
            ? round(($estadisticas['entregados'] / $estadisticas['total']) * 100) 
            : 0;

        return view('bodega.index', compact('pedidos', 'estadisticas', 'estadisticasHoy'));
    }

    /**
     * API: Obtener todos los pedidos en formato JSON
     */
    public function obtenerPedidos(): JsonResponse
    {
        $pedidos = Pedido::orderBy('created_at', 'desc')->get();
        $estadisticas = Pedido::estadisticasGenerales();
        $estadisticasHoy = Pedido::estadisticasHoy();
        
        $estadisticas['eficiencia'] = $estadisticas['total'] > 0 
            ? round(($estadisticas['entregados'] / $estadisticas['total']) * 100) 
            : 0;

        return response()->json([
            'success' => true,
            'pedidos' => $pedidos->map(function($pedido) {
                return [
                    'id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'cliente' => $pedido->cliente,
                    'telefono' => $pedido->telefono,
                    'direccion' => $pedido->direccion,
                    'forma_pago' => $pedido->forma_pago,
                    'productos' => $pedido->productos,
                    'monto_total' => $pedido->monto_total,
                    'monto_formateado' => $pedido->monto_formateado,
                    'estado' => $pedido->estado,
                    'estado_badge' => $pedido->estado_badge,
                    'prioridad' => $pedido->prioridad,
                    'prioridad_badge' => $pedido->prioridad_badge,
                    'metodo_envio' => $pedido->metodo_envio,
                    'metodo_envio_badge' => $pedido->metodo_envio_badge,
                    'cajas_estimadas' => $pedido->cajas_estimadas,
                    'cajas_finales' => $pedido->cajas_finales,
                    'cajas_info' => $pedido->cajas_info,
                    'numero_guia' => $pedido->numero_guia,
                    'observaciones_envio' => $pedido->observaciones_envio,
                    'fecha_entrega' => $pedido->fecha_entrega,
                    'fecha_entrega_formateada' => $pedido->fecha_entrega_formateada,
                    'dias_para_entrega' => $pedido->dias_para_entrega,
                    'info_adicional' => $pedido->info_adicional,
                    'fecha_creacion' => $pedido->created_at->toISOString(),
                    'fecha_creacion_formateada' => $pedido->fecha_creacion_formateada,
                    'fecha_actualizacion' => $pedido->updated_at->toISOString(),
                    'esta_atrasado' => $pedido->estaAtrasado(),
                    'es_urgente' => $pedido->esUrgente(),
                    'tiene_guia' => $pedido->tieneGuiaAsignada(),
                    'puede_ser_procesado' => $pedido->puedeSerProcesado(),
                    'puede_ser_entregado' => $pedido->puedeSerEntregado()
                ];
            }),
            'estadisticas' => $estadisticas,
            'estadisticas_hoy' => $estadisticasHoy
        ]);
    }

    /**
     * Ver detalle completo de un pedido
     */
    public function verDetalle($id): JsonResponse
    {
        $pedido = Pedido::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'pedido' => $pedido->toApiArray()
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN DE ESTADOS
    |--------------------------------------------------------------------------
    */

    /**
     * Cambiar estado de un pedido
     */
    public function cambiarEstado(Request $request, $id): JsonResponse
    {
        $request->validate([
            'estado' => ['required', Rule::in(array_keys(Pedido::ESTADOS))]
        ]);

        $pedido = Pedido::findOrFail($id);
        $estadoAnterior = $pedido->estado;
        $pedido->estado = $request->estado;
        $pedido->usuario_procesamiento = auth()->id();
        $pedido->save();

        Log::info("Estado cambiado - Pedido #{$id}: {$estadoAnterior} → {$request->estado}");

        return response()->json([
            'success' => true,
            'message' => "✅ Pedido #{$pedido->numero_pedido} cambiado de '{$estadoAnterior}' a '{$request->estado}'",
            'pedido' => $pedido->toApiArray()
        ]);
    }

    /**
     * Procesar un pedido (cambiar a "Preparando")
     */
    public function procesarPedido($id)
    {
        $pedido = Pedido::findOrFail($id);

        if (!$pedido->puedeSerProcesado()) {
            return back()->with('warning', '⚠️ Este pedido ya está siendo procesado.');
        }

        $pedido->estado = 'Preparando';
        $pedido->usuario_procesamiento = auth()->id();
        $pedido->save();

        return redirect()->route('bodega.index')
               ->with('success', "⚙️ Pedido #{$pedido->numero_pedido} en preparación.");
    }

    /**
     * Marcar un pedido como entregado
     */
    public function marcarEntregado($id)
    {
        $pedido = Pedido::findOrFail($id);

        if ($pedido->estado === 'Entregado') {
            return back()->with('info', '⚠️ Este pedido ya fue entregado.');
        }

        $pedido->marcarComoEntregado(auth()->id());

        return redirect()->route('bodega.index')
               ->with('success', "✅ Pedido #{$pedido->numero_pedido} marcado como entregado.");
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN DE ENVÍOS
    |--------------------------------------------------------------------------
    */

    /**
     * Configurar envío individual (modal principal)
     */
    public function configurarEnvio(Request $request, $id): JsonResponse
    {
        $request->validate([
            'metodo_envio' => ['required', Rule::in(array_keys(Pedido::METODOS_ENVIO))],
            'cajas_finales' => 'required|integer|min:1|max:50',
            'numero_guia' => 'nullable|string|max:100',
            'observaciones_envio' => 'nullable|string|max:1000'
        ]);

        try {
            $pedido = Pedido::findOrFail($id);

            $pedido->configurarEnvio(
                $request->metodo_envio,
                $request->cajas_finales,
                $request->numero_guia,
                $request->observaciones_envio,
                auth()->id()
            );

            Log::info("Envío configurado - Pedido #{$id}: {$request->metodo_envio}, {$request->cajas_finales} cajas");

            return response()->json([
                'success' => true,
                'message' => "✅ Envío configurado para pedido #{$pedido->numero_pedido}",
                'pedido' => $pedido->fresh()->toApiArray()
            ]);

        } catch (\Exception $e) {
            Log::error("Error configurando envío - Pedido #{$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al configurar el envío: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configuración rápida por método de envío
     */
    public function configuracionRapida(Request $request): JsonResponse
    {
        $request->validate([
            'metodo_envio' => ['required', Rule::in(array_keys(Pedido::METODOS_ENVIO))],
            'pedidos' => 'required|array|min:1',
            'pedidos.*' => 'exists:pedidos,id',
            'cajas_finales' => 'required|integer|min:1|max:50',
            'prefijo_guia' => 'nullable|string|max:10'
        ]);

        try {
            DB::beginTransaction();

            $pedidos = Pedido::whereIn('id', $request->pedidos)
                           ->where('estado', 'Por Procesar')
                           ->get();

            $configurados = 0;
            $contador = 1;

            foreach ($pedidos as $pedido) {
                $numeroGuia = null;
                if ($request->prefijo_guia) {
                    $numeroGuia = $request->prefijo_guia . str_pad($contador, 6, '0', STR_PAD_LEFT);
                    $contador++;
                }

                $pedido->configurarEnvio(
                    $request->metodo_envio,
                    $request->cajas_finales,
                    $numeroGuia,
                    "Configuración rápida - {$request->metodo_envio}",
                    auth()->id()
                );

                $configurados++;
            }

            DB::commit();

            Log::info("Configuración rápida aplicada: {$configurados} pedidos con {$request->metodo_envio}");

            return response()->json([
                'success' => true,
                'message' => "✅ {$configurados} pedidos configurados con {$request->metodo_envio}",
                'configurados' => $configurados
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error en configuración rápida: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar configuración rápida: ' . $e->getMessage()
            ], 500);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | BÚSQUEDA Y FILTROS
    |--------------------------------------------------------------------------
    */

    /**
     * Buscar pedidos con filtros
     */
    public function buscar(Request $request): JsonResponse
    {
        $query = Pedido::query();

        // Búsqueda por término
        if ($termino = $request->get('q')) {
            $query->buscar($termino);
        }

        // Filtro por estado
        if ($estado = $request->get('estado')) {
            $query->estado($estado);
        }

        // Filtro por prioridad
        if ($prioridad = $request->get('prioridad')) {
            $query->prioridad($prioridad);
        }

        // Filtro por método de envío
        if ($metodo = $request->get('metodo_envio')) {
            $query->metodoEnvio($metodo);
        }

        $pedidos = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'pedidos' => $pedidos->map(fn($p) => $p->toApiArray()),
            'total' => $pedidos->count()
        ]);
    }

    /**
     * Filtrar por estado
     */
    public function filtrarPorEstado($estado): JsonResponse
    {
        $pedidos = Pedido::estado($estado)
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'success' => true,
            'pedidos' => $pedidos->map(fn($p) => $p->toApiArray()),
            'total' => $pedidos->count(),
            'filtro' => "Estado: {$estado}"
        ]);
    }

    /**
     * Filtrar por método de envío
     */
    public function filtrarPorMetodo($metodo): JsonResponse
    {
        $pedidos = Pedido::metodoEnvio($metodo)
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'success' => true,
            'pedidos' => $pedidos->map(fn($p) => $p->toApiArray()),
            'total' => $pedidos->count(),
            'filtro' => "Método: {$metodo}"
        ]);
    }

    /**
     * Filtrar por prioridad
     */
    public function filtrarPorPrioridad($prioridad): JsonResponse
    {
        $pedidos = Pedido::prioridad($prioridad)
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'success' => true,
            'pedidos' => $pedidos->map(fn($p) => $p->toApiArray()),
            'total' => $pedidos->count(),
            'filtro' => "Prioridad: {$prioridad}"
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN MASIVA
    |--------------------------------------------------------------------------
    */

    /**
     * Procesar múltiples pedidos pendientes
     */
    public function procesarPendientes(Request $request): JsonResponse
    {
        try {
            $procesados = Pedido::porProcesar()
                               ->update([
                                   'estado' => 'Preparando',
                                   'usuario_procesamiento' => auth()->id(),
                                   'updated_at' => now()
                               ]);

            return response()->json([
                'success' => true,
                'message' => "⚡ {$procesados} pedidos procesados automáticamente",
                'procesados' => $procesados
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar pedidos pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar todos los pedidos entregados
     */
    public function eliminarEntregados()
    {
        $cantidad = Pedido::entregados()->count();
        Pedido::entregados()->delete();
        
        Log::info("Limpieza masiva: {$cantidad} pedidos entregados eliminados");

        return redirect()->route('bodega.index')
               ->with('success', "🗑 {$cantidad} pedidos entregados eliminados.");
    }

    /**
     * Eliminar un pedido específico
     */
    public function eliminarPedido($id): JsonResponse
    {
        try {
            $pedido = Pedido::findOrFail($id);
            $numeroPedido = $pedido->numero_pedido;
            $pedido->delete();

            Log::info("Pedido eliminado: #{$id} - {$numeroPedido}");

            return response()->json([
                'success' => true,
                'message' => "🗑️ Pedido #{$numeroPedido} eliminado correctamente"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajustar cajas masivamente
     */
    public function ajustarCajasMasivo(Request $request): JsonResponse
    {
        $request->validate([
            'pedidos' => 'required|array|min:1',
            'pedidos.*' => 'exists:pedidos,id',
            'cajas_finales' => 'required|integer|min:1|max:50'
        ]);

        try {
            $actualizados = Pedido::whereIn('id', $request->pedidos)
                                 ->update([
                                     'cajas_finales' => $request->cajas_finales,
                                     'usuario_procesamiento' => auth()->id(),
                                     'updated_at' => now()
                                 ]);

            return response()->json([
                'success' => true,
                'message' => "✅ {$actualizados} pedidos actualizados con {$request->cajas_finales} cajas",
                'actualizados' => $actualizados
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ajustar cajas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pedidos disponibles para configuración rápida por método
     */
    public function pedidosDisponiblesPorMetodo($metodo): JsonResponse
    {
        if (!array_key_exists($metodo, Pedido::METODOS_ENVIO)) {
            return response()->json([
                'success' => false,
                'message' => 'Método de envío no válido'
            ], 400);
        }

        $pedidos = Pedido::where('metodo_envio', $metodo)
                        ->where('estado', 'Por Procesar')
                        ->orderBy('prioridad', 'desc')
                        ->orderBy('created_at', 'asc')
                        ->get();

        return response()->json([
            'success' => true,
            'pedidos' => $pedidos->map(function($pedido) {
                return [
                    'id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'cliente' => $pedido->cliente,
                    'direccion' => $pedido->direccion,
                    'cajas_estimadas' => $pedido->cajas_estimadas,
                    'prioridad' => $pedido->prioridad,
                    'prioridad_badge' => $pedido->prioridad_badge,
                    'fecha_creacion' => $pedido->fecha_creacion_formateada
                ];
            }),
            'total' => $pedidos->count()
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | ESTADÍSTICAS Y REPORTES
    |--------------------------------------------------------------------------
    */

    /**
     * Estadísticas generales para dashboard
     */
    public function estadisticasGenerales(): JsonResponse
    {
        $estadisticas = Pedido::estadisticasGenerales();
        $estadisticasHoy = Pedido::estadisticasHoy();
        $estadisticasEnvio = Pedido::estadisticasPorMetodoEnvio();

        return response()->json([
            'success' => true,
            'estadisticas_generales' => $estadisticas,
            'estadisticas_hoy' => $estadisticasHoy,
            'estadisticas_envio' => $estadisticasEnvio
        ]);
    }

    /**
     * Estadísticas por método de envío
     */
    public function estadisticasPorEnvio(): JsonResponse
    {
        $estadisticas = Pedido::estadisticasPorMetodoEnvio();

        return response()->json([
            'success' => true,
            'estadisticas' => $estadisticas
        ]);
    }

    /**
     * Obtener último ID para notificaciones en tiempo real
     */
    public function ultimoId(): JsonResponse
    {
        $ultimoPedido = Pedido::latest('id')->first();
        
        return response()->json([
            'success' => true,
            'ultimo_id' => $ultimoPedido ? $ultimoPedido->id : 0,
            'total_pedidos' => Pedido::count()
        ]);
    }

    /**
     * Verificar nuevos pedidos para alertas
     */
    public function verificarNuevosPedidos(Request $request): JsonResponse
    {
        $ultimoIdConocido = $request->get('ultimo_id', 0);
        
        $nuevosPedidos = Pedido::where('id', '>', $ultimoIdConocido)
                              ->orderBy('id', 'desc')
                              ->get();

        $hayNuevos = $nuevosPedidos->count() > 0;

        return response()->json([
            'success' => true,
            'hay_nuevos' => $hayNuevos,
            'cantidad_nuevos' => $nuevosPedidos->count(),
            'nuevos_pedidos' => $hayNuevos ? $nuevosPedidos->map(function($pedido) {
                return [
                    'id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'cliente' => $pedido->cliente,
                    'prioridad' => $pedido->prioridad,
                    'metodo_envio' => $pedido->metodo_envio,
                    'fecha_creacion' => $pedido->fecha_creacion_formateada
                ];
            }) : [],
            'ultimo_id' => $nuevosPedidos->first()?->id ?? $ultimoIdConocido
        ]);
    }

    /**
     * Actualizar número de guía
     */
    public function actualizarNumeroGuia(Request $request, $id): JsonResponse
    {
        $request->validate([
            'numero_guia' => 'required|string|max:100'
        ]);

        try {
            $pedido = Pedido::findOrFail($id);
            $pedido->numero_guia = $request->numero_guia;
            $pedido->usuario_procesamiento = auth()->id();
            $pedido->save();

            return response()->json([
                'success' => true,
                'message' => "📄 Número de guía actualizado para pedido #{$pedido->numero_pedido}",
                'numero_guia' => $pedido->numero_guia
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar número de guía: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar observaciones de envío
     */
    public function actualizarObservaciones(Request $request, $id): JsonResponse
    {
        $request->validate([
            'observaciones_envio' => 'nullable|string|max:1000'
        ]);

        try {
            $pedido = Pedido::findOrFail($id);
            $pedido->observaciones_envio = $request->observaciones_envio;
            $pedido->usuario_procesamiento = auth()->id();
            $pedido->save();

            return response()->json([
                'success' => true,
                'message' => "💬 Observaciones actualizadas para pedido #{$pedido->numero_pedido}",
                'observaciones' => $pedido->observaciones_envio
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar observaciones: ' . $e->getMessage()
            ], 500);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | EXPORTACIÓN Y REPORTES - MÉTODOS NUEVOS 🆕
    |--------------------------------------------------------------------------
    */

    /**
     * Exportar pedidos a Excel/CSV 🆕
     */
   public function exportar(Request $request)
{
    try {
        // Log para debug
        \Log::info('Exportación CSV mejorada solicitada', $request->all());
        
        // Obtener pedidos
        $pedidos = Pedido::orderBy('created_at', 'desc')->get();
        
        \Log::info('Pedidos encontrados: ' . $pedidos->count());
        
        // Preparar datos CSV estructurados
        $csvData = [];
        
        // === TÍTULO DEL REPORTE ===
        $fechaActual = date('d/m/Y H:i:s');
        $csvData[] = ['REPORTE DE BODEGA - SISTEMA DE GESTIÓN DE PEDIDOS'];
        $csvData[] = ['Generado el: ' . $fechaActual];
        $csvData[] = ['Total de registros: ' . $pedidos->count()];
        $csvData[] = []; // Línea vacía
        
        // === RESUMEN ESTADÍSTICO ===
        $entregados = $pedidos->where('estado', 'Entregado')->count();
        $preparando = $pedidos->whereIn('estado', ['Preparando', 'PREPARANDO'])->count();
        $pendientes = $pedidos->whereIn('estado', ['Por Procesar', 'POR PROCESAR', 'Pendiente', ''])->count();
        
        $csvData[] = ['=== RESUMEN ESTADÍSTICO ==='];
        $csvData[] = ['Estado', 'Cantidad', 'Porcentaje'];
        $csvData[] = ['Entregados', $entregados, $pedidos->count() > 0 ? round(($entregados / $pedidos->count()) * 100, 1) . '%' : '0%'];
        $csvData[] = ['En Preparación', $preparando, $pedidos->count() > 0 ? round(($preparando / $pedidos->count()) * 100, 1) . '%' : '0%'];
        $csvData[] = ['Pendientes', $pendientes, $pedidos->count() > 0 ? round(($pendientes / $pedidos->count()) * 100, 1) . '%' : '0%'];
        $csvData[] = []; // Línea vacía
        
        // === RESUMEN POR MÉTODO DE ENVÍO ===
        $metodos = $pedidos->groupBy('metodo_envio');
        $csvData[] = ['=== DISTRIBUCIÓN POR MÉTODO DE ENVÍO ==='];
        $csvData[] = ['Método de Envío', 'Cantidad', 'Porcentaje'];
        
        foreach ($metodos as $metodo => $pedidosMetodo) {
            $cantidad = $pedidosMetodo->count();
            $porcentaje = $pedidos->count() > 0 ? round(($cantidad / $pedidos->count()) * 100, 1) . '%' : '0%';
            $csvData[] = [$metodo ?: 'Sin método asignado', $cantidad, $porcentaje];
        }
        
        $csvData[] = []; // Línea vacía
        $csvData[] = []; // Línea vacía
        
        // === DETALLE COMPLETO DE PEDIDOS ===
        $csvData[] = ['=== DETALLE COMPLETO DE PEDIDOS ==='];
        
        // Encabezados principales con mejor formato
        $csvData[] = [
            'ID PEDIDO',
            'NÚMERO PEDIDO', 
            'CLIENTE',
            'TELÉFONO',
            'DIRECCIÓN COMPLETA',
            'PRODUCTOS SOLICITADOS',
            'FORMA DE PAGO',
            'MONTO TOTAL (Q)',
            'ESTADO ACTUAL',
            'PRIORIDAD',
            'MÉTODO DE ENVÍO',
            'CAJAS ESTIMADAS',
            'CAJAS FINALES',
            'NÚMERO DE GUÍA',
            'FECHA DE ENTREGA',
            'FECHA DE CREACIÓN',
            'OBSERVACIONES'
        ];
        
        // Datos de pedidos con formato mejorado
        foreach ($pedidos as $pedido) {
            // Formatear estado con mayúsculas
            $estadoFormateado = strtoupper($pedido->estado ?: 'POR PROCESAR');
            
            // Formatear prioridad
            $prioridadFormateada = strtoupper($pedido->prioridad ?: 'MEDIA');
            
            // Formatear monto
            $montoFormateado = $pedido->monto_total ? 'Q' . number_format($pedido->monto_total, 2) : 'No especificado';
            
            // Formatear fechas
            $fechaCreacion = $pedido->created_at ? $pedido->created_at->format('d/m/Y H:i:s') : 'No registrada';
            $fechaEntrega = $pedido->fecha_entrega ? date('d/m/Y', strtotime($pedido->fecha_entrega)) : 'No programada';
            
            // Formatear cajas
            $cajasEstimadas = $pedido->cajas_estimadas ?: 'No estimadas';
            $cajasFinales = $pedido->cajas_finales ?: 'No configuradas';
            
            $csvData[] = [
                $pedido->id,
                $pedido->numero_pedido ?: 'No asignado',
                $pedido->cliente ?: 'Sin nombre',
                $pedido->telefono ?: 'No proporcionado',
                $pedido->direccion ?: 'Dirección no especificada',
                $pedido->productos ?: 'Productos no detallados',
                $pedido->forma_pago ?: 'No especificada',
                $montoFormateado,
                $estadoFormateado,
                $prioridadFormateada,
                $pedido->metodo_envio ?: 'No asignado',
                $cajasEstimadas,
                $cajasFinales,
                $pedido->numero_guia ?: 'No asignada',
                $fechaEntrega,
                $fechaCreacion,
                $pedido->observaciones_envio ?: 'Sin observaciones'
            ];
        }
        
        // === PIE DE REPORTE ===
        $csvData[] = []; // Línea vacía
        $csvData[] = ['=== INFORMACIÓN DEL SISTEMA ==='];
        $csvData[] = ['Sistema:', 'Gestión de Bodega v1.0'];
        $csvData[] = ['Empresa:', 'SBG']; // Personaliza aquí
        $csvData[] = ['Exportado por:', 'Sistema Automatizado'];
        $csvData[] = ['Formato:', 'CSV compatible con Excel'];
        $csvData[] = ['Encoding:', 'UTF-8 con BOM'];
        
        // Generar nombre del archivo más descriptivo
        $fecha = date('Y-m-d');
        $hora = date('H-i-s');
        $filename = "REPORTE_BODEGA_{$fecha}_{$hora}_({$pedidos->count()}_pedidos).csv";
        
        // Crear respuesta CSV mejorada
        $callback = function() use ($csvData) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8 (para que Excel abra correctamente los acentos)
            fwrite($file, "\xEF\xBB\xBF");
            
            foreach ($csvData as $row) {
                // Usar punto y coma como separador (mejor para Excel en español)
                fputcsv($file, $row, ';');
            }
            
            fclose($file);
        };
        
        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error exportando CSV mejorado: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Error generando reporte: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
}
    /**
     * Exportar datos a CSV 🆕
     */
    private function exportarCSV($datos, $nombreArchivo)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        return response()->streamDownload(function() use ($datos) {
            $handle = fopen('php://output', 'w');
            
            // BOM para UTF-8 (para que Excel muestre acentos correctamente)
            fwrite($handle, "\xEF\xBB\xBF");
            
            // Escribir encabezados
            if ($datos->count() > 0) {
                fputcsv($handle, array_keys($datos->first()));
            }
            
            // Escribir datos
            foreach ($datos as $fila) {
                fputcsv($handle, $fila);
            }
            
            fclose($handle);
        }, $nombreArchivo, $headers);
    }

    /**
     * Exportar reporte por rango de fechas 🆕
     */
    public function exportarPorRango(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        try {
            $pedidos = Pedido::whereBetween('created_at', [
                              $request->fecha_inicio . ' 00:00:00',
                              $request->fecha_fin . ' 23:59:59'
                          ])
                          ->orderBy('created_at', 'desc')
                          ->get();

            $nombreArchivo = "pedidos_{$request->fecha_inicio}_a_{$request->fecha_fin}.csv";
            
            $datos = $pedidos->map(function($pedido) {
                return [
                    'ID' => $pedido->id,
                    'Cliente' => $pedido->cliente,
                    'Estado' => $pedido->estado,
                    'Monto' => $pedido->monto_total,
                    'Fecha' => $pedido->created_at->format('Y-m-d H:i:s')
                ];
            });

            return $this->exportarCSV($datos, $nombreArchivo);

        } catch (\Exception $e) {
            return back()->with('error', 'Error en exportación por rango: ' . $e->getMessage());
        }
    }

    /**
     * Exportar por método de envío 🆕
     */
    public function exportarPorMetodo($metodo)
    {
        try {
            if (!array_key_exists($metodo, Pedido::METODOS_ENVIO)) {
                return back()->with('error', 'Método de envío no válido');
            }

            $pedidos = Pedido::where('metodo_envio', $metodo)
                            ->orderBy('created_at', 'desc')
                            ->get();

            $nombreArchivo = "pedidos_" . str_replace(' ', '_', strtolower($metodo)) . "_" . now()->format('Y-m-d') . ".csv";
            
            $datos = $pedidos->map(function($pedido) {
                return [
                    'ID' => $pedido->id,
                    'Cliente' => $pedido->cliente,
                    'Direccion' => $pedido->direccion,
                    'Estado' => $pedido->estado,
                    'Cajas' => $pedido->cajas_finales ?? $pedido->cajas_estimadas,
                    'Numero_Guia' => $pedido->numero_guia,
                    'Fecha' => $pedido->created_at->format('Y-m-d H:i:s')
                ];
            });

            return $this->exportarCSV($datos, $nombreArchivo);

        } catch (\Exception $e) {
            return back()->with('error', 'Error en exportación por método: ' . $e->getMessage());
        }
    }

    /**
     * Reporte detallado 🆕
     */
    public function reporteDetallado(): JsonResponse
    {
        try {
            $estadisticas = Pedido::estadisticasGenerales();
            $estadisticasHoy = Pedido::estadisticasHoy();
            $estadisticasEnvio = Pedido::estadisticasPorMetodoEnvio();
            
            // Estadísticas por prioridad
            $prioridades = Pedido::selectRaw('prioridad, COUNT(*) as total')
                               ->groupBy('prioridad')
                               ->pluck('total', 'prioridad');

            // Pedidos atrasados
            $atrasados = Pedido::where('fecha_entrega', '<', today())
                              ->where('estado', '!=', 'Entregado')
                              ->get();

            // Eficiencia por método de envío
            $eficienciaPorMetodo = [];
            foreach (Pedido::METODOS_ENVIO as $metodo => $nombre) {
                $totalMetodo = Pedido::where('metodo_envio', $metodo)->count();
                $entregadosMetodo = Pedido::where('metodo_envio', $metodo)
                                        ->where('estado', 'Entregado')
                                        ->count();
                
                $eficienciaPorMetodo[$metodo] = $totalMetodo > 0 
                    ? round(($entregadosMetodo / $totalMetodo) * 100) 
                    : 0;
            }

            return response()->json([
                'success' => true,
                'reporte' => [
                    'fecha_generacion' => now()->format('Y-m-d H:i:s'),
                    'estadisticas_generales' => $estadisticas,
                    'estadisticas_hoy' => $estadisticasHoy,
                    'estadisticas_envio' => $estadisticasEnvio,
                    'estadisticas_prioridad' => $prioridades,
                    'pedidos_atrasados' => $atrasados->count(),
                    'eficiencia_por_metodo' => $eficienciaPorMetodo,
                    'total_cajas_despachadas' => Pedido::where('estado', 'Entregado')->sum('cajas_finales'),
                    'promedio_cajas_por_pedido' => Pedido::avg('cajas_finales') ?? 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar seguimiento (placeholder para integración futura) 🆕
     */
    public function consultarSeguimiento($numeroGuia): JsonResponse
    {
        try {
            $pedido = Pedido::where('numero_guia', $numeroGuia)->first();
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró pedido con ese número de guía'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'seguimiento' => [
                    'numero_guia' => $numeroGuia,
                    'pedido_id' => $pedido->id,
                    'estado_interno' => $pedido->estado,
                    'cliente' => $pedido->cliente,
                    'metodo_envio' => $pedido->metodo_envio,
                    'fecha_envio' => $pedido->updated_at->format('Y-m-d H:i:s'),
                    'observaciones' => $pedido->observaciones_envio,
                    'nota' => 'Integración con paqueterías pendiente'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error consultando seguimiento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como vista (placeholder) 🆕
     */
    public function marcarNotificacionVista($id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como vista'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marcando notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selección masiva por criterios 🆕
     */
    public function seleccionMasiva(Request $request): JsonResponse
    {
        $request->validate([
            'criterio' => 'required|in:estado,prioridad,metodo_envio',
            'valor' => 'required|string'
        ]);

        try {
            $query = Pedido::query();
            
            switch ($request->criterio) {
                case 'estado':
                    $pedidos = $query->where('estado', $request->valor)->get();
                    break;
                case 'prioridad':
                    $pedidos = $query->where('prioridad', $request->valor)->get();
                    break;
                case 'metodo_envio':
                    $pedidos = $query->where('metodo_envio', $request->valor)->get();
                    break;
            }

            return response()->json([
                'success' => true,
                'pedidos_seleccionados' => $pedidos->pluck('id'),
                'total_seleccionados' => $pedidos->count(),
                'mensaje' => "Se seleccionaron {$pedidos->count()} pedidos por {$request->criterio}: {$request->valor}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en selección masiva: ' . $e->getMessage()
            ], 500);
        }
    }
}
// 🔚 FIN DE LA CLASE PedidoController