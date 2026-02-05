<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Services\Clients\ClientService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Clients\Client;
use App\Rules\ValidEcuadorianCedula;
use Illuminate\Validation\Rule;


class ClientController extends Controller
{
    protected ClientService $service;

    public function __construct(ClientService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'tipo', 'estado', 'ciudad']);
        $perPage = (int) $request->get('per_page', 15);

        $clients = $this->service->list($filters, $perPage);

        if ($request->wantsJson() || $request->ajax()) {

            // si viene ?all=1, devolvemos SOLO el array de clientes
            if ($request->boolean('all')) {
                return response()->json($clients->items());
            }

            return response()->json($clients); // paginador completo
        }

        return view('clients.index', compact('clients', 'filters'));
    }

    public function export(Request $request)
    {
        return $this->service->exportClients($request);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $client = $this->service->create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Cliente creado correctamente',
                'data'    => $client,
            ], Response::HTTP_CREATED);
        }
        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function show(int $id)
    {
        $client = $this->service->find($id);

        return response()->json($client);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validateData($request, $id);

        $client = $this->service->update($id, $data);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Cliente actualizado correctamente',
                'data'    => $client,
            ]);
        }

        return redirect()
            ->route('clients.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Cliente eliminado correctamente',
            ], Response::HTTP_OK);
        }

        return redirect()
            ->route('clients.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }

    public function findByIdentificacion(Request $request)
    {
        $data = $request->validate([
            'tipo_identificacion' => 'required|in:CEDULA,RUC,PASAPORTE',
            'identificacion'      => 'required|string|max:20',
        ]);

        $client = $this->service->findByIdentificacion(
            $data['tipo_identificacion'],
            $data['identificacion']
        );

        if (!$client) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json($client);
    }

    public function emails(int $id)
    {
        $client = Client::with('emails')->find($id);

        if (! $client) {
            return response()->json([], Response::HTTP_NOT_FOUND);
        }

        $emails = $client->emails
            ->pluck('email')   
            ->filter()       
            ->values()
            ->toArray();

        return response()->json($emails);
    }



    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $tipoIdentificacion = $request->input('tipo_identificacion');
        $identificacionRule = ['required', 'string', 'max:20'];

        if ($tipoIdentificacion) {
            $unique = Rule::unique('clients', 'identificacion')
                ->where(fn ($q) => $q->where('tipo_identificacion', $tipoIdentificacion));

            if ($ignoreId) $unique->ignore($ignoreId);

            $identificacionRule[] = $unique;
        }

        if ($tipoIdentificacion === 'CEDULA') {
            $identificacionRule[] = new ValidEcuadorianCedula();
        }

        if ($tipoIdentificacion === 'RUC') {
            $identificacionRule[] = 'digits:13';
        }


        return $request->validate([
            'tipo_identificacion' => ['required', Rule::in(['CEDULA','RUC','PASAPORTE'])],
            'identificacion'      => $identificacionRule,

            'business'            => ['required', 'string', 'max:191'],
            'tipo'                => ['required', Rule::in(['natural','juridico'])],
            'telefono'            => ['nullable', 'string', 'max:50'],
            'direccion'           => ['nullable', 'string', 'max:255'],
            'ciudad'              => ['nullable', 'string', 'max:100'],
            'estado'              => ['required', Rule::in(['activo','inactivo'])],

            'emails'              => ['nullable', 'array'],
            'emails.*'            => ['nullable', 'email:rfc,dns', 'max:191'],
        ]);
    }
}
