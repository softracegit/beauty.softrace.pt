<?php

namespace App\Http\Controllers;

use App\Services\ExceptionReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;

class DevErrorTestController extends Controller
{
    public function index(): Response
    {
        return response()->view('dev.error-test', [
            'reportEnabled' => (bool) config('errors.report_enabled'),
            'reportRecipients' => config('errors.report_recipients', []),
            'appDebug' => (bool) config('app.debug'),
            'appEnv' => (string) config('app.env'),
        ]);
    }

    public function previewPage(): Response
    {
        $reference = (string) Str::uuid();
        app()->instance(ExceptionReportService::REFERENCE_KEY, $reference);

        return response()->view('errors.500', [], 500);
    }

    public function triggerEmail(): JsonResponse
    {
        $exception = new RuntimeException('[TESTE] Erro simulado para validar relatório por email.');

        $reference = app(ExceptionReportService::class)->report($exception);

        return response()->json([
            'ok' => true,
            'message' => 'Pedido de relatório processado. Verifique a caixa de entrada (ou MAIL_TEST_REDIRECT_TO em ambiente local).',
            'reference' => $reference,
            'report_enabled' => (bool) config('errors.report_enabled'),
            'recipients' => config('errors.report_recipients', []),
            'app_debug' => (bool) config('app.debug'),
            'app_env' => (string) config('app.env'),
        ]);
    }

    public function triggerException(): never
    {
        throw new RuntimeException('[TESTE] Erro simulado — fluxo completo (página + email quando APP_DEBUG=false).');
    }
}
