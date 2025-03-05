<?php

namespace Model\Base;

use Illuminate\Database\Eloquent\Model;

class SecuenciaENCF extends Model
{
    protected $connection = 'tenant';
    protected $table = 'secuencias_encf';
    
    protected $fillable = [
        'tipo_comprobante',
        'serie',
        'secuencial_desde',
        'secuencial_hasta',
        'secuencial_actual',
        'fecha_vencimiento',
        'activo'
    ];

    protected $casts = [
        'secuencial_desde' => 'integer',
        'secuencial_hasta' => 'integer',
        'secuencial_actual' => 'integer',
        'fecha_vencimiento' => 'date',
        'activo' => 'boolean'
    ];

    public function siguienteSecuencial(): ?string
    {
        if (!$this->activo || $this->secuencial_actual >= $this->secuencial_hasta) {
            return null;
        }

        $this->secuencial_actual++;
        $this->save();

        // Formato: serie + tipo_comprobante + secuencial (8 dígitos)
        return $this->serie . $this->tipo_comprobante . str_pad($this->secuencial_actual, 8, '0', STR_PAD_LEFT);
    }

    public function disponibles(): int
    {
        if (!$this->activo) {
            return 0;
        }
        return max(0, $this->secuencial_hasta - ($this->secuencial_actual ?? $this->secuencial_desde - 1));
    }

    public function validarSecuencia(): bool
    {
        // Validar que la secuencia sea válida según normas DGII
        if ($this->secuencial_desde > $this->secuencial_hasta) {
            return false;
        }

        if ($this->fecha_vencimiento < date('Y-m-d')) {
            $this->activo = false;
            $this->save();
            return false;
        }

        return true;
    }
} 