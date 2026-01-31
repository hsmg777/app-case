<?php

namespace App\Http\Controllers\Sri;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sri\UpsertSriConfigRequest;
use App\Services\Sri\SriConfigService;

class SriConfigController extends Controller
{
    public function __construct(private SriConfigService $service)
    {
    }

    public function edit()
    {
        $config = $this->service->get();

        return view('sri.config', [
            'config' => $config,
            'envHasPassword' => (bool) env('SRI_CERT_PASSWORD'),
        ]);
    }

    public function store(UpsertSriConfigRequest $request)
    {
        try {
            $data = $request->validated();

            $certFile = $request->file('certificado_p12');
            unset($data['certificado_p12']);

            $config = $this->service->save($data, $certFile);

            return redirect()
                ->route('sri.config.edit')
                ->with('success', '✅ Configuración SRI guardada correctamente. El certificado ha sido convertido y validado.')
                ->with('clear_form', true);
        } catch (\Exception $e) {
            return redirect()
                ->route('sri.config.edit')
                ->withErrors(['sri' => $e->getMessage()])
                ->withInput();
        }
    }

    public function testConfig()
    {
        try {
            $msg = $this->service->testCertificate();
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['sri' => $e->getMessage()]);
        }
    }

}
