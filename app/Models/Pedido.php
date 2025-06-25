<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Pedido extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_pedido',
        'cliente',
        'telefono',
        'direccion',
        'forma_pago',
        'monto_total',
        'productos',
        'info_adicional',
        'estado',
        'prioridad',
        'metodo_envio',
        'cajas_estimadas',
        'cajas_finales',
        'numero_guia',
        'observaciones_envio',
        'fecha_entrega',
        'fecha_creacion_local',
        'usuario_creacion',
        'usuario_procesamiento'
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'fecha_entrega' => 'date',
        'cajas_estimadas' => 'integer',
        'cajas_finales' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Constantes para valores enum
    const ESTADOS = [
        'Por Procesar' => 'Por Procesar',
        'Preparando' => 'Preparando',
        'Entregado' => 'Entregado'
    ];

    const PRIORIDADES = [
        'Baja' => 'Baja',
        'Media' => 'Media',
        'Alta' => 'Alta',
        'Urgente' => 'Urgente'
    ];

    const FORMAS_PAGO = [
        'Credito' => 'Credito',
        'Por Cobrar' => 'Por Cobrar'
    ];

    const METODOS_ENVIO = [
        'Guatex' => 'Guatex',
        'Cargo Expreso' => 'Cargo Expreso',
        'Entrega Local' => 'Entrega Local'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */
    
    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    public function usuarioProcesamiento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_procesamiento');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES PARA FILTROS
    |--------------------------------------------------------------------------
    */
    
    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePrioridad($query, $prioridad)
    {
        return $query->where('prioridad', $prioridad);
    }

    public function scopeMetodoEnvio($query, $metodo)
    {
        return $query->where('metodo_envio', $metodo);
    }

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

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['Por Procesar', 'Preparando']);
    }

    public function scopeHoy($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeUrgentes($query)
    {
        return $query->where('prioridad', 'Urgente');
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where(function($q) use ($termino) {
            $q->where('cliente', 'LIKE', "%{$termino}%")
              ->orWhere('direccion', 'LIKE', "%{$termino}%")
              ->orWhere('productos', 'LIKE', "%{$termino}%")
              ->orWhere('numero_pedido', 'LIKE', "%{$termino}%")
              ->orWhere('numero_guia', 'LIKE', "%{$termino}%")
              ->orWhere('id', 'LIKE', "%{$termino}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS PARA MOSTRAR DATOS FORMATEADOS
    |--------------------------------------------------------------------------
    */
    
    public function getEstadoBadgeAttribute()
    {
        $badges = [
            'Por Procesar' => '🟡',
            'Preparando' => '🔵',
            'Entregado' => '🟢'
        ];
        return ($badges[$this->estado] ?? '⚪') . ' ' . $this->estado;
    }

    public function getPrioridadBadgeAttribute()
    {
        $badges = [
            'Baja' => '🟢',
            'Media' => '🟡',
            'Alta' => '🔴',
            'Urgente' => '🚨'
        ];
        return ($badges[$this->prioridad] ?? '⚪') . ' ' . $this->prioridad;
    }

    public function getMetodoEnvioBadgeAttribute()
    {
        $badges = [
            'Guatex' => '📮',
            'Cargo Expreso' => '🚛',
            'Entrega Local' => '🏠'
        ];
        return ($badges[$this->metodo_envio] ?? '📦') . ' ' . ($this->metodo_envio ?? 'Sin definir');
    }

    public function getMontoFormateadoAttribute()
    {
        return 'Q' . number_format($this->monto_total, 2);
    }

    public function getFechaCreacionFormateadaAttribute()
    {
        return $this->created_at->format('d/m/Y H:i:s');
    }

    public function getFechaEntregaFormateadaAttribute()
    {
        return $this->fecha_entrega ? $this->fecha_entrega->format('d/m/Y') : 'No especificada';
    }

    public function getDiasParaEntregaAttribute()
    {
        if (!$this->fecha_entrega) {
            return null;
        }

        $hoy = Carbon::today();
        $fechaEntrega = Carbon::parse($this->fecha_entrega);

        if ($fechaEntrega->isToday()) {
            return 'Hoy';
        } elseif ($fechaEntrega->isTomorrow()) {
            return 'Mañana';
        } elseif ($fechaEntrega->isPast()) {
            return 'Atrasado ' . $fechaEntrega->diffForHumans($hoy);
        } else {
            return $fechaEntrega->diffForHumans($hoy);
        }
    }

    public function getCajasInfoAttribute()
    {
        if ($this->cajas_finales) {
            return "{$this->cajas_finales} cajas (confirmadas)";
        } else {
            return "{$this->cajas_estimadas} cajas (estimadas)";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS DE NEGOCIO
    |--------------------------------------------------------------------------
    */
    
    public function puedeSerProcesado(): bool
    {
        return $this->estado === 'Por Procesar';
    }

    public function puedeSerEntregado(): bool
    {
        return $this->estado === 'Preparando';
    }

    public function estaAtrasado(): bool
    {
        return $this->fecha_entrega && Carbon::parse($this->fecha_entrega)->isPast() && $this->estado !== 'Entregado';
    }

    public function esUrgente(): bool
    {
        return $this->prioridad === 'Urgente';
    }

    public function tieneGuiaAsignada(): bool
    {
        return !empty($this->numero_guia);
    }

    public function configurarEnvio($metodoEnvio, $cajasFinales, $numeroGuia = null, $observaciones = null, $usuarioId = null)
    {
        $this->update([
            'metodo_envio' => $metodoEnvio,
            'cajas_finales' => $cajasFinales,
            'numero_guia' => $numeroGuia,
            'observaciones_envio' => $observaciones,
            'usuario_procesamiento' => $usuarioId,
            'estado' => 'Preparando'
        ]);

        return $this;
    }

    public function marcarComoEntregado($usuarioId = null)
    {
        $this->update([
            'estado' => 'Entregado',
            'usuario_procesamiento' => $usuarioId ?? $this->usuario_procesamiento
        ]);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS ESTÁTICOS PARA ESTADÍSTICAS
    |--------------------------------------------------------------------------
    */
    
    public static function estadisticasGenerales()
    {
        return [
            'por_procesar' => self::porProcesar()->count(),
            'preparando' => self::preparando()->count(),
            'entregados' => self::entregados()->count(),
            'total' => self::count(),
            'urgentes' => self::urgentes()->count(),
            'atrasados' => self::where('fecha_entrega', '<', today())
                              ->where('estado', '!=', 'Entregado')
                              ->count()
        ];
    }

    public static function estadisticasHoy()
    {
        $pedidosHoy = self::hoy()->get();
        
        return [
            'pedidos_hoy' => $pedidosHoy->count(),
            'ventas_hoy' => $pedidosHoy->sum('monto_total'),
            'cajas_estimadas_hoy' => $pedidosHoy->sum('cajas_estimadas'),
            'cajas_despachadas_hoy' => $pedidosHoy->sum('cajas_finales'),
            'pendientes' => self::pendientes()->count()
        ];
    }

    public static function estadisticasPorMetodoEnvio()
    {
        return self::selectRaw('metodo_envio, COUNT(*) as total, SUM(cajas_finales) as cajas_totales')
                   ->whereNotNull('metodo_envio')
                   ->groupBy('metodo_envio')
                   ->get()
                   ->keyBy('metodo_envio');
    }

    // Generar número de pedido único
    public static function generarNumeroPedido()
    {
        $fecha = now()->format('Ymd');
        $ultimoPedido = self::where('numero_pedido', 'LIKE', "PED{$fecha}%")
                           ->orderBy('numero_pedido', 'desc')
                           ->first();

        if ($ultimoPedido) {
            $ultimoNumero = (int) substr($ultimoPedido->numero_pedido, -4);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return "PED{$fecha}" . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMATEO PARA API
    |--------------------------------------------------------------------------
    */
    
    public function toApiArray()
    {
        return [
            'id' => $this->id,
            'numero_pedido' => $this->numero_pedido,
            'cliente' => $this->cliente,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'forma_pago' => $this->forma_pago,
            'monto_total' => $this->monto_total,
            'productos' => $this->productos,
            'info_adicional' => $this->info_adicional,
            'estado' => $this->estado,
            'prioridad' => $this->prioridad,
            'metodo_envio' => $this->metodo_envio,
            'cajas_estimadas' => $this->cajas_estimadas,
            'cajas_finales' => $this->cajas_finales,
            'numero_guia' => $this->numero_guia,
            'observaciones_envio' => $this->observaciones_envio,
            'fecha_entrega' => $this->fecha_entrega?->format('Y-m-d'),
            'fecha_creacion' => $this->created_at?->toISOString(),
            'fecha_actualizacion' => $this->updated_at?->toISOString(),
            'estado_badge' => $this->estado_badge,
            'prioridad_badge' => $this->prioridad_badge,
            'metodo_envio_badge' => $this->metodo_envio_badge,
            'monto_formateado' => $this->monto_formateado,
            'fecha_creacion_formateada' => $this->fecha_creacion_formateada,
            'fecha_entrega_formateada' => $this->fecha_entrega_formateada,
            'dias_para_entrega' => $this->dias_para_entrega,
            'cajas_info' => $this->cajas_info
        ];
    }
}