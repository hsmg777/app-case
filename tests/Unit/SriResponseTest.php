<?php

namespace Tests\Unit;

use App\Services\Sri\SriInvoiceService;
use App\Services\Sri\SriConfigService;
use App\Repositories\Sri\ElectronicInvoiceRepository;
use Tests\TestCase;

class SriResponseTest extends TestCase
{
    /** @test */
    public function it_detects_error_70_in_deeply_nested_structure()
    {
        // Mock dependencies
        $configService = $this->createMock(SriConfigService::class);
        $repo = $this->createMock(ElectronicInvoiceRepository::class);

        $service = new SriInvoiceService($configService, $repo);

        // Caso reportado por el usuario: doble anidación de 'recep'
        $xmlResponse = [
            'recep' => [
                'recep' => [
                    'estado' => 'DEVUELTA',
                    'mensajes' => [
                        [
                            'identificador' => '70',
                            'mensaje' => 'CLAVE DE ACCESO EN PROCESAMIENTO',
                            'informacionAdicional' => 'La clave de acceso esta en procesamiento VALOR DEVUELTO POR EL PROCEDIMIENTO: SI'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertTrue($service->hasProcessing70($xmlResponse), 'Debería detectar el identificador 70 en estructura anidada');
        $this->assertFalse($service->isRecibida($xmlResponse), 'No debería marcar como RECIBIDA si el estado es DEVUELTA');
    }

    /** @test */
    public function it_detects_error_70_by_text_content()
    {
        $configService = $this->createMock(SriConfigService::class);
        $repo = $this->createMock(ElectronicInvoiceRepository::class);
        $service = new SriInvoiceService($configService, $repo);

        $xmlResponse = [
            'mensajes' => [
                [
                    'identificador' => 'ABC',
                    'mensaje' => 'LA FACTURA YA EXISTE EN EL SISTEMA',
                    'informacionAdicional' => 'PROCEDIMIENTO: SI'
                ]
            ]
        ];

        $this->assertTrue($service->hasProcessing70($xmlResponse), 'Debería detectar por texto "YA EXISTE" o "PROCEDIMIENTO: SI"');
    }

    /** @test */
    public function it_detects_recibida_in_nested_structure()
    {
        $configService = $this->createMock(SriConfigService::class);
        $repo = $this->createMock(ElectronicInvoiceRepository::class);
        $service = new SriInvoiceService($configService, $repo);

        $xmlResponse = [
            'resultado' => [
                'recep' => [
                    'estado' => 'RECIBIDA'
                ]
            ]
        ];

        $this->assertTrue($service->isRecibida($xmlResponse), 'Debería encontrar "estado" => "RECIBIDA" recursivamente');
    }
}
