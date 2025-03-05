<?php

namespace Model\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleComprobante extends Model
{
    protected $connection = 'tenant';
    protected $table = 'detalles_comprobantes';
    
    protected $fillable = [
        'comprobante_id',
        'numero_linea',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'monto_item',
        'tipo_ingreso'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'monto_item' => 'decimal:2'
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class, 'comprobante_id');
    }

    public function calcularMontoItem()
    {
        $subtotal = $this->cantidad * $this->precio_unitario;
        $this->monto_item = $subtotal - ($this->descuento ?? 0);
        return $this->monto_item;
    }

    public function calcularITBIS()
    {
        // TODO: Implementar cálculo de ITBIS según tipo de ingreso y normativa DGII
        return $this->monto_item * 0.18; // Tasa estándar de ITBIS
    }
} 