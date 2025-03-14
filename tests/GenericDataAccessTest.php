<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../src/Model/Base/paginaparasignaDataaccess.php';


class GenericDataAccessTest extends TestCase
{
    private $dataAccess;

    protected function setUp(): void
    {
        $this->dataAccess = new paginaparasignaDataacces();
    }

    public function testAddAndRetrieveRelation()
    {
        // Datos de ejemplo: Avión y Asientos
        $planeKey = 'plane1';
        $planeData = 'Boeing 747';
        $seatKey1 = 'seat1';
        $seatData1 = '1A';
        $seatKey2 = 'seat2';
        $seatData2 = '1B';

        // Agregar relaciones
        $this->dataAccess->addRelation($planeKey, $planeData, $seatKey1, $seatData1);
        $this->dataAccess->addRelation($planeKey, $planeData, $seatKey2, $seatData2);

        // Verificar el dato del lado "uno"
        $this->assertEquals($planeData, $this->dataAccess->getOne($planeKey));

        // Verificar los datos del lado "muchos"
        $seats = $this->dataAccess->getMany($planeKey);
        $this->assertCount(2, $seats);
        $this->assertEquals($seatData1, $seats[$seatKey1]);
        $this->assertEquals($seatData2, $seats[$seatKey2]);
    }

    public function testGetAllRelations()
    {
        // Datos de ejemplo: Otro Avión y Asientos
        $planeKey = 'plane2';
        $planeData = 'Airbus A320';
        $seatKey = 'seat1';
        $seatData = '2A';

        // Agregar relación
        $this->dataAccess->addRelation($planeKey, $planeData, $seatKey, $seatData);

        // Verificar todas las relaciones
        $allRelations = $this->dataAccess->getAllRelations();
        $this->assertArrayHasKey($planeKey, $allRelations);
        $this->assertEquals($planeData, $allRelations[$planeKey]['one']);
        $this->assertEquals($seatData, $allRelations[$planeKey]['many'][$seatKey]);
    }
}