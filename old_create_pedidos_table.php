<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('pedidos', function (Blueprint $table) {
     
    $table->id();
    $table->string('cliente');
    $table->string('telefono')->nullable();
    $table->text('direccion');
    $table->string('forma_pago');
    $table->text('productos');
    $table->string('prioridad')->nullable();
    $table->date('fecha_entrega')->nullable();
    $table->text('info_adicional')->nullable();
    $table->string('estado')->default('pendiente');
    $table->timestamps();
});


      
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('pedidos');
    }
};
