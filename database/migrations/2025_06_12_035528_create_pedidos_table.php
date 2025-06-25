<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            
            // Número de pedido único
            $table->string('numero_pedido')->unique();
            
            // Información del cliente
            $table->string('cliente');
            $table->string('telefono')->nullable();
            $table->text('direccion');
            
            // Información del pedido
            $table->enum('forma_pago', ['Credito', 'Por Cobrar']);
            $table->decimal('monto_total', 10, 2)->nullable();
            $table->text('productos');
            $table->text('info_adicional')->nullable();
            
            // Estado y prioridad
            $table->enum('estado', ['Por Procesar', 'Preparando', 'Entregado'])->default('Por Procesar');
            $table->enum('prioridad', ['Baja', 'Media', 'Alta', 'Urgente'])->default('Media');
            
            // Información de envío
            $table->enum('metodo_envio', ['Guatex', 'Cargo Expreso', 'Entrega Local'])->nullable();
            $table->integer('cajas_estimadas')->nullable();
            $table->integer('cajas_finales')->nullable();
            $table->string('numero_guia')->nullable();
            $table->text('observaciones_envio')->nullable();
            
            // Fechas
            $table->date('fecha_entrega')->nullable();
            $table->string('fecha_creacion_local')->nullable();
            
            // Usuarios
            $table->unsignedBigInteger('usuario_creacion')->nullable();
            $table->unsignedBigInteger('usuario_procesamiento')->nullable();
            
            // Timestamps de Laravel
            $table->timestamps();
            
            // Índices para mejorar performance
            $table->index('estado');
            $table->index('prioridad');
            $table->index('metodo_envio');
            $table->index('fecha_entrega');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};