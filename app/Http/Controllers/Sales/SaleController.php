<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Store\Bodega;
use App\Models\Sales\PaymentMethod;

class SaleController extends Controller
{
    protected SaleService $service;

    public function __construct(SaleService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | VISTAS
    |--------------------------------------------------------------------------
    */

    public function viewIndex(Bodega $bodega)
    {
        $paymentMethods = PaymentMethod::where('activo', true)->get();

        return view('sales.index', [
            'bodegaSelected' => $bodega,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function viewSelectBodega()
    {
        $bodegas = Bodega::all();
        return view('sales.select-bodega', compact('bodegas'));
    }

    /*
    |--------------------------------------------------------------------------
    | ENDPOINTS JSON
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'      => 'nullable|exists:clients,id',
            'user_id'        => 'required|exists:users,id',
            'bodega_id'      => 'required|exists:bodegas,id',
            'caja_id'        => 'required|integer|min:1',
            'client_email_id' => 'nullable|exists:client_emails,id',
            'email_destino'   => 'nullable|string|max:255',
            'fecha_venta'    => 'required|date',
            'tipo_documento' => 'nullable|string|max:20',
            'num_factura'    => 'nullable|string|max:50',
            'observaciones'  => 'nullable|string|max:500',

            'iva_enabled'    => 'nullable|boolean',

            'items'                   => 'required|array|min:1',
            'items.*.producto_id'     => 'required|exists:products,id',
            'items.*.descripcion'     => 'required|string|max:255',
            'items.*.cantidad'        => 'required|integer|min:1',

            // backend recalcula por product_prices
            'items.*.precio_unitario' => 'nullable|numeric|min:0',

            // descuento es MONTO ($)
            'items.*.descuento'       => 'nullable|numeric|min:0',

            'items.*.iva_porcentaje'  => 'nullable|numeric|min:0|max:100',
            'items.*.percha_id'       => 'nullable|exists:perchas,id',

            'payment'                   => 'required|array',
            'payment.metodo'            => 'required|string|max:20',
            'payment.payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment.monto_recibido'    => 'required|numeric|min:0',
            'payment.referencia'        => 'nullable|string|max:100',
            'payment.observaciones'     => 'nullable|string|max:500',
            'payment.fecha_pago'        => 'nullable|date',
        ]);

        $sale = $this->service->crearVenta($data);

        $message = 'Venta registrada correctamente.';
        if ($sale->vendio_sin_stock ?? false) {
            $message .= ' ⚠ OJO: uno o más productos se vendieron sin stock suficiente (inventario negativo).';
        }

        return response()->json([
            'message' => $message,
            'data'    => $sale,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $sale = $this->service->getById((int) $id);
        return response()->json($sale);
    }
}
