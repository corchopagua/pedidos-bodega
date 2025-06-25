<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Events\NuevoPedido;

class Pedido extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pedidos';

    protected $fillable = [
        // Información del cliente
        'cliente',
        'telefono',
        'direccion',
        
        // Información del pedido
        'pago',
        'prioridad',
        'fecha_entrega',
        'monto_total',
        'productos',
        'informacion_adicional',
        
        // Estado del pedido
        'estado',
        
        // Información de envío (desde ventas)
        'metodo_envio_estimado',
        'cajas_estimadas',
        
        // Información final de envío (desde bodega)
        'numero_cajas',
        'metodo_envio',
        'numero_guia',
        'observaciones_envio',
        
        // Timestamps personalizados
        'fecha_creacion',
        'fecha_actualizacion',
        'fecha_completado'
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'cajas_estimadas' => 'integer',
        'numero_cajas' => 'integer',
        'fecha_entrega' => 'date',
        'fecha_creacion' => 'datetime',
        'fecha_actualizacion' => 'datetime',
        'fecha_completado' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $dates = [
        'fecha_entrega',
        'fecha_creacion',
        'fecha_actualizacion',
        'fecha_completado',
        'deleted_at'
    ];

    // Desactivar timestamps automáticos de Laravel
    public $timestamps = false;

    // Estados válidos
    const ESTADOS = [
        'Por Procesar',
        'Preparando',
        'Entregado'
    ];

    // Prioridades válidas
    const PRIORIDADES = [
        'Baja',
        'Media',
        'Alta',
        'Urgente'
    ];

    // Métodos de pago válidos
    const METODOS_PAGO = [
        'Credito',
        'Por Cobrar'
    ];

    // Métodos de envío válidos
    const METODOS_ENVIO = [
        'Guatex',
        'Cargo Expreso',
        'Entrega Local',
        'Pickup'
    ];

    /**
     * Boot del modelo para eventos
     */
    protected static function booted()
    {
        // Evento al crear un pedido
        static::created(function ($pedido) {
            // Disparar evento de broadcasting
            broadcast(new NuevoPedido($pedido))->toOthers();
        });

        // Evento al actualizar un pedido
        static::updating(function ($pedido) {
            $pedido->fecha_actualizacion = now();
            
            // Si se marca como entregado, guardar fecha de completado
            if ($pedido->isDirty('estado') && $pedido->estado === 'Entregado') {
                $pedido->fecha_completado = now();
            }
        });

        // Evento al crear, establecer fecha de creación
        static::creating(function ($pedido) {
            $pedido->fecha_creacion = now();
            $pedido->fecha_actualizacion = now();
            
            // Estado por defecto
            if (empty($pedido->estado)) {
                $pedido->estado = 'Por Procesar';
            }
            
            // Prioridad por defecto
            if (empty($pedido->prioridad)) {
                $pedido->prioridad = 'Media';
            }
        });
    }

    /**
     * Scopes para consultas frecuentes
     */
    public function scopePorProcesar($query)
    {
        return $query->where('estado', 'Por Procesar');
    }

    public function scopePreparando($query)
    {
        return $query->where('estado', 'Preparando');
    }

    public function scopeEntregados($query)
    {
        return $query->where('estado', 'Entregado');
    }

    public function scopePrioridadAlta($query)
    {
        return $query->whereIn('prioridad', ['Alta', 'Urgente']);
    }

    public function scopeHoy($query)
    {
        return $query->whereDate('fecha_creacion', today());
    }

    public function scopePorMetodoEnvio($query, $metodo)
    {
        return $query->where('metodo_envio', $metodo);
    }

    /**
     * Accessors y Mutators
     */
    public function getEsPrioridadAltaAttribute()
    {
        return in_array($this->prioridad, ['Alta', 'Urgente']);
    }

    public function getEsUrgenteAttribute()
    {
        return $this->prioridad === 'Urgente';
    }

    public function getEstaConfiguradoAttribute()
    {
        return !empty($this->numero_cajas) && !empty($this->metodo_envio);
    }

    public function getDiasDesdeCreacionAttribute()
    {
        return $this->fecha_creacion ? $this->fecha_creacion->diffInDays(now()) : 0;
    }

    public function getResumenAttribute()
    {
        return [
            'id' => $this->id,
            'cliente' => $this->cliente,
            'estado' => $this->estado,
            'prioridad' => $this->prioridad,
            'cajas' => $this->numero_cajas ?? $this->cajas_estimadas,
            'metodo_envio' => $this->metodo_envio ?? $this->metodo_envio_estimado,
            'monto' => $this->monto_total,
            'fecha_creacion' => $this->fecha_creacion?->format('d/m/Y H:i')
        ];
    }

    /**
     * Métodos de negocio
     */
    public function puedeSerConfigurado()
    {
        return $this->estado === 'Por Procesar';
    }

    public function puedeSerEntregado()
    {
        return $this->estado === 'Preparando' && $this->esta_configurado;
    }

    public function configurarEnvio($datos)
    {
        $this->update([
            'numero_cajas' => $datos['numero_cajas'],
            'metodo_envio' => $datos['metodo_envio'],
            'numero_guia' => $datos['numero_guia'] ?? null,
            'observaciones_envio' => $datos['observaciones_envio'] ?? null,
            'estado' => 'Preparando'
        ]);

        return $this;
    }

    public function marcarComoEntregado()
    {
        $this->update([
            'estado' => 'Entregado',
            'fecha_completado' => now()
        ]);

        return $this;
    }

    /**
     * Métodos estáticos para estadísticas
     */
    public static function estadisticasHoy()
    {
        $hoy = today();
        
        return [
            'total_hoy' => static::whereDate('fecha_creacion', $hoy)->count(),
            'por_procesar' => static::porProcesar()->count(),
            'preparando' => static::preparando()->count(),
            'entregados_hoy' => static::entregados()->whereDate('fecha_completado', $hoy)->count(),
            'ventas_hoy' => static::whereDate('fecha_creacion', $hoy)->sum('monto_total'),
            'cajas_despachadas_hoy' => static::entregados()
                ->whereDate('fecha_completado', $hoy)
                ->sum('numero_cajas'),
            'eficiencia' => static::calcularEficiencia()
        ];
    }

    public static function estadisticasPorMetodoEnvio()
    {
        return static::selectRaw('metodo_envio, COUNT(*) as total, SUM(numero_cajas) as total_cajas')
            ->whereNotNull('metodo_envio')
            ->groupBy('metodo_envio')
            ->get()
            ->keyBy('metodo_envio');
    }

    public static function calcularEficiencia()
    {
        $total = static::count();
        $entregados = static::entregados()->count();
        
        return $total > 0 ? round(($entregados / $total) * 100, 2) : 0;
    }

    /**
     * Validaciones personalizadas
     */
    public static function validarDatos($datos)
    {
        $reglas = [
            'cliente' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'required|string',
            'pago' => 'required|in:' . implode(',', static::METODOS_PAGO),
            'prioridad' => 'required|in:' . implode(',', static::PRIORIDADES),
            'fecha_entrega' => 'nullable|date|after_or_equal:today',
            'monto_total' => 'nullable|numeric|min:0',
            'productos' => 'required|string',
            'informacion_adicional' => 'nullable|string',
            'metodo_envio_estimado' => 'nullable|in:' . implode(',', static::METODOS_ENVIO),
            'cajas_estimadas' => 'nullable|integer|min:1|max:50'
        ];

        return validator($datos, $reglas);
    }

    public static function validarConfiguracionEnvio($datos)
    {
        $reglas = [
            'numero_cajas' => 'required|integer|min:1|max:50',
            'metodo_envio' => 'required|in:' . implode(',', static::METODOS_ENVIO),
            'numero_guia' => 'nullable|string|max:50',
            'observaciones_envio' => 'nullable|string|max:1000'
        ];

        return validator($datos, $reglas);
    }

    /**
     * Formateo para API
     */
    public function toApiArray()
    {
        return [
            'id' => $this->id,
            'cliente' => $this->cliente,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'pago' => $this->pago,
            'prioridad' => $this->prioridad,
            'fechaEntrega' => $this->fecha_entrega?->format('Y-m-d'),
            'montoTotal' => $this->monto_total,
            'productos' => $this->productos,
            'informacionAdicional' => $this->informacion_adicional,
            'estado' => $this->estado,
            
            // Información de envío estimada (ventas)
            'metodoEnvioEstimado' => $this->metodo_envio_estimado,
            'cajasEstimadas' => $this->cajas_estimadas,
            
            // Información de envío final (bodega)
            'numeroCajas' => $this->numero_cajas,
            'metodoEnvio' => $this->metodo_envio,
            'numeroGuia' => $this->numero_guia,
            'observacionesEnvio' => $this->observaciones_envio,
            
            // Fechas
            'fechaCreacion' => $this->fecha_creacion?->toISOString(),
            'fechaActualizacion' => $this->fecha_actualizacion?->toISOString(),
            'fechaCompletado' => $this->fecha_completado?->toISOString(),
            
            // Campos calculados
            'esPrioridadAlta' => $this->es_prioridad_alta,
            'esUrgente' => $this->es_urgente,
            'estaConfigurado' => $this->esta_configurado,
            'diasDesdeCreacion' => $this->dias_desde_creacion
        ];
    }
}