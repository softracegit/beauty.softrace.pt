<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingClientAuthController;
use App\Http\Controllers\BookingContactVerificationController;
use App\Http\Controllers\BookingAccountCancelController;
use App\Http\Controllers\BookingAccountWalletController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\BookingSmsActionController;
use App\Http\Controllers\BookingSavedCardController;
use App\Http\Controllers\BookingSlotHoldController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AgendaDepositController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CurrentStoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DefinicoesController;
use App\Http\Controllers\ExtraController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MarketingSmsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\RelatoriosController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SuperAdmin\OrganizationController as SuperAdminOrganizationController;
use App\Http\Controllers\SuperAdmin\OrganizationStoreController;
use App\Http\Controllers\VisitController;
use App\Models\Store;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Booking (marcação pública — mesmo domínio, prefixo /booking)
|--------------------------------------------------------------------------
| Middleware `booking`: contexto do fluxo (ver App\Http\Middleware\BookingContext).
| Assets estáticos: public/booking-assets/{css,js,img} (não usar public/booking — conflita com a rota /booking)
*/
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('/termos', [LegalController::class, 'terms'])->name('terms');
    Route::get('/privacidade', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/cookies', [LegalController::class, 'cookies'])->name('cookies');
});

Route::get('/booking', function () {
    return redirect()->route('booking.index', [
        'store' => Store::defaultPublicBookingStoreSlug(),
    ]);
});

Route::get('/r/{token}', [BookingSmsActionController::class, 'manage'])
    ->middleware('throttle:60,1')
    ->name('booking.sms.manage');
Route::post('/r/{token}/confirm', [BookingSmsActionController::class, 'confirm'])
    ->middleware('throttle:60,1')
    ->name('booking.sms.confirm');
Route::post('/r/{token}/cancel', [BookingSmsActionController::class, 'cancel'])
    ->middleware('throttle:60,1')
    ->name('booking.sms.cancel');

Route::prefix('booking/{store:slug}')->middleware(['booking', 'booking.locale'])->name('booking.')->group(function () {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::get('/session/ping', [BookingController::class, 'sessionPing'])
        ->middleware('throttle:120,1')
        ->name('session.ping');
    Route::get('/staff', [BookingController::class, 'technician'])->name('technician');
    Route::get('/disponiblidade', [BookingController::class, 'datetime'])->name('datetime');
    Route::get('/disponibilidade', [BookingController::class, 'availability'])->name('availability');
    Route::get('/checkout', [BookingController::class, 'step3'])->name('step3');
    Route::get('/politica-cancelamento/preview', [BookingController::class, 'cancellationPolicyPreview'])
        ->middleware('throttle:60,1')
        ->name('cancellation.preview');
    Route::post('/marcacao/confirmar', [BookingPaymentController::class, 'confirmWithoutPayment'])
        ->middleware('throttle:30,1')
        ->name('confirm.without_payment');
    Route::post('/pagamento/intencao', [BookingPaymentController::class, 'createIntent'])
        ->middleware('throttle:20,1')
        ->name('payment.intent');
    Route::post('/pagamento/finalizar', [BookingPaymentController::class, 'complete'])
        ->middleware('throttle:30,1')
        ->name('payment.complete');
    Route::get('/confirmacao', [BookingController::class, 'confirm'])->name('confirm');
    Route::get('/servico/{service}', [BookingController::class, 'showService'])->name('service');

    Route::post('/slot-hold/acquire', [BookingSlotHoldController::class, 'acquire'])
        ->middleware('throttle:30,1')
        ->name('slot_hold.acquire');
    Route::post('/slot-hold/extend', [BookingSlotHoldController::class, 'extend'])
        ->middleware('throttle:30,1')
        ->name('slot_hold.extend');
    Route::post('/slot-hold/release', [BookingSlotHoldController::class, 'release'])
        ->middleware('throttle:30,1')
        ->name('slot_hold.release');

    Route::get('/login', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        $store = $request->route('store');
        $storeSlug = $store instanceof Store ? $store->slug : null;
        if ($storeSlug === null) {
            $storeSlug = Store::defaultPublicBookingStoreSlug();
        }
        if ($user instanceof \App\Models\User && $user->isBookingClient()) {
            return redirect()->route('booking.conta.index', ['store' => $storeSlug]);
        }
        if ($user) {
            return redirect()->route('dashboard');
        }
        $email = (string) $request->query('email', '');
        $params = ['store' => $storeSlug, 'open_auth' => '1'];
        if ($email !== '') {
            $params['email'] = $email;
        }

        return redirect()->route('booking.index', $params);
    })->name('login');

    /*
     * Passwordless booking auth (código por email).
     * Sem middleware `guest`: com sessão CRM (staff) aberta no mesmo browser, `guest` faria redirect
     * e o fetch receberia HTML em vez de JSON.
     */
    Route::post('/auth/request-code', [BookingClientAuthController::class, 'requestCodeFromAuthModal'])
        ->middleware('throttle:20,1')
        ->name('auth.request_code');
    Route::post('/auth/verify-code', [BookingClientAuthController::class, 'verifyCodeFromAuthModal'])
        ->middleware('throttle:10,1')
        ->name('auth.verify_code');
    Route::post('/auth/complete-registration', [BookingClientAuthController::class, 'completeRegistrationFromAuthModal'])
        ->middleware('throttle:10,1')
        ->name('auth.complete_registration');

    /*
     * Logout por GET: evita 419 quando o separador fica aberto muito tempo (token CSRF do POST expira).
     * Apenas `booking.client`; o backoffice continua a usar POST /logout com @csrf.
     */
    Route::middleware(['auth', 'booking.client'])
        ->get('/sair', [AuthController::class, 'logout'])
        ->middleware('throttle:30,1')
        ->name('logout');

    Route::middleware(['auth', 'booking.client'])->prefix('conta')->name('conta.')->group(function () {
        Route::get('/', [BookingController::class, 'account'])->name('index');
        Route::get('/marcacoes', [BookingController::class, 'appointments'])->name('marcacoes');
        Route::get('/carteira', [BookingAccountWalletController::class, 'index'])->name('carteira');
        Route::post('/marcacoes/{calendarEvent}/cancelar', [BookingAccountCancelController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('marcacoes.cancel');
        Route::get('/definicoes', [BookingController::class, 'settings'])->name('settings');
        Route::post('/dados-pessoais', [BookingController::class, 'updateProfilePersonal'])
            ->middleware('throttle:30,1')
            ->name('profile.personal');
        Route::post('/notificacoes', [BookingController::class, 'updateNotificationPreferences'])
            ->middleware('throttle:30,1')
            ->name('notifications.update');
        Route::post('/verificacao/request', [BookingContactVerificationController::class, 'requestCode'])->name('verification.request');
        Route::post('/verificacao/confirm', [BookingContactVerificationController::class, 'confirmCode'])->name('verification.confirm');
        Route::post('/cartoes/setup-intent', [BookingSavedCardController::class, 'createSetupIntent'])->name('cards.setup_intent');
        Route::post('/cartoes/sync', [BookingSavedCardController::class, 'syncAfterSetupIntent'])->name('cards.sync');
        Route::post('/cartoes/{card}/default', [BookingSavedCardController::class, 'makeDefault'])->name('cards.default');
        Route::delete('/cartoes/{card}', [BookingSavedCardController::class, 'destroy'])->name('cards.destroy');
    });

    // URL pública do membro (/booking/{loja}/{slug}) — tem de ficar por último entre as GET literais.
    Route::get('/{agent:booking_slug}', [BookingController::class, 'indexForAgent'])->name('agent');
});

// Rotas de autenticação (públicas)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
});

// Logout (autenticado)
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'super.admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('super-admin.organizations.index');
    })->name('dashboard');
    Route::resource('organizations.stores', OrganizationStoreController::class)
        ->scoped()
        ->except(['index', 'show']);
    Route::resource('organizations', SuperAdminOrganizationController::class);
});

// Rotas protegidas (requerem autenticação e agent associado)
Route::middleware(['auth', 'has.agent', 'set.current.store', 'backoffice.access', 'log.page.view'])->group(function () {
    Route::post('loja-activa', [CurrentStoreController::class, 'update'])->name('current-store.update');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'resumo'])->name('dashboard');
    Route::get('/dashboard/marcacoes', [DashboardController::class, 'marcacoes'])->name('dashboard.marcacoes');
    Route::get('/dashboard/imoveis', [DashboardController::class, 'imoveis'])->name('dashboard.imoveis');
    Route::get('/dashboard/negocios', [DashboardController::class, 'negocios'])->name('dashboard.negocios');
    Route::get('/dashboard/clientes', [DashboardController::class, 'clientes'])->name('dashboard.clientes');
    Route::get('/dashboard/ocupacao', [DashboardController::class, 'ocupacao'])->name('dashboard.ocupacao');
    Route::get('/dashboard/financeiro', [DashboardController::class, 'financeiro'])->name('dashboard.financeiro');

    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('/activity/navegacao', [ActivityLogController::class, 'navigation'])->name('activity.navigation');

    Route::prefix('ajuda')->name('ajuda.')->group(function () {
        Route::get('/', [HelpController::class, 'index'])->name('index');
        Route::get('/agenda', [HelpController::class, 'agenda'])->name('agenda');
    });

    Route::get('notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notificacoes/api', [NotificationController::class, 'apiList'])->name('notifications.api');
    Route::post('notificacoes/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notificacoes/{id}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('id')
        ->name('notifications.read');

    Route::prefix('definicoes')->name('definicoes.')->group(function () {
        Route::get('/', [DefinicoesController::class, 'index'])->name('index');
        Route::get('negocio', [DefinicoesController::class, 'negocio'])->name('negocio');
        Route::post('negocio', [DefinicoesController::class, 'updateNegocio'])->name('negocio.update');
        Route::get('marcacoes', [DefinicoesController::class, 'marcacoes'])->name('marcacoes');
        Route::post('marcacoes', [DefinicoesController::class, 'updateMarcacoes'])->name('marcacoes.update');
        Route::get('equipa', [DefinicoesController::class, 'equipa'])->name('equipa');
        Route::post('equipa', [DefinicoesController::class, 'updateEquipa'])->name('equipa.update');
        Route::get('notificacoes', [DefinicoesController::class, 'notificacoes'])->name('notificacoes');
        Route::post('notificacoes', [DefinicoesController::class, 'updateNotificacoes'])->name('notificacoes.update');
        Route::get('pagamentos', [DefinicoesController::class, 'pagamentos'])->name('pagamentos');
        Route::post('pagamentos', [DefinicoesController::class, 'updatePagamentos'])->name('pagamentos.update');
    });

    Route::get('clientes/export', [ClientController::class, 'indexExport'])->name('clientes.export');
    Route::get('clientes/pdf', [ClientController::class, 'indexPdf'])->name('clientes.pdf');
    Route::resource('clientes', ClientController::class);
    Route::post('clientes/{cliente}/notes', [ClientController::class, 'storeNote'])->name('clientes.storeNote');

    Route::resource('equipa', AgentController::class)->parameters(['equipa' => 'agente']);
    Route::post('equipa/{agente}/notes', [AgentController::class, 'storeNote'])->name('equipa.storeNote');

    // Rotas de Leads
    Route::get('leads/kanban', [LeadController::class, 'kanban'])->name('leads.kanban');
    Route::post('leads/{lead}/update-status', [LeadController::class, 'updateStatus'])->name('leads.updateStatus');
    Route::post('leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.storeNote');
    Route::post('leads/{lead}/convert-to-opportunity', [LeadController::class, 'convertToOpportunity'])->name('leads.convertToOpportunity');
    Route::post('leads/{lead}/archive', [LeadController::class, 'archive'])->name('leads.archive');
    Route::post('leads/{lead}/restore', [LeadController::class, 'restore'])->name('leads.restore');
    Route::resource('leads', LeadController::class);

    // Rotas de Imóveis
    Route::get('properties/get-cities', [PropertyController::class, 'getCitiesByDistrict'])->name('properties.getCities');
    Route::get('properties/get-parishes', [PropertyController::class, 'getParishesByCity'])->name('properties.getParishes');
    Route::post('properties/{property}/notes', [PropertyController::class, 'storeNote'])->name('properties.storeNote');
    Route::resource('properties', PropertyController::class);

    // Rotas de Oportunidades
    Route::get('opportunities/kanban', [OpportunityController::class, 'kanban'])->name('opportunities.kanban');
    Route::post('opportunities/{opportunity}/update-status', [OpportunityController::class, 'updateStatus'])->name('opportunities.updateStatus');
    Route::get('opportunities/{opportunity}/crossed-properties', [OpportunityController::class, 'getCrossedProperties'])->name('opportunities.getCrossedProperties');
    Route::post('opportunities/{opportunity}/attach-property', [OpportunityController::class, 'attachProperty'])->name('opportunities.attachProperty');
    Route::post('opportunities/{opportunity}/detach-property', [OpportunityController::class, 'detachProperty'])->name('opportunities.detachProperty');
    Route::post('opportunities/{opportunity}/archive', [OpportunityController::class, 'archive'])->name('opportunities.archive');
    Route::post('opportunities/{opportunity}/restore', [OpportunityController::class, 'restore'])->name('opportunities.restore');
    Route::post('opportunities/{opportunity}/notes', [OpportunityController::class, 'storeNote'])->name('opportunities.storeNote');
    Route::post('opportunities/{opportunity}/visits', [VisitController::class, 'store'])->name('opportunities.visits.store');
    Route::put('visits/{visit}', [VisitController::class, 'update'])->name('visits.update');
    Route::post('opportunities/{opportunity}/proposals', [ProposalController::class, 'store'])->name('opportunities.proposals.store');
    Route::put('proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
    Route::post('proposals/{proposal}/approve', [ProposalController::class, 'approve'])->name('proposals.approve');
    Route::post('proposals/{proposal}/reject', [ProposalController::class, 'reject'])->name('proposals.reject');
    Route::post('proposals/{proposal}/counter-proposal', [ProposalController::class, 'storeCounterProposal'])->name('proposals.counterProposal');
    Route::resource('opportunities', OpportunityController::class);

    // Rotas de Deals (Negócios Fechados)
    Route::get('deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    Route::post('opportunities/{opportunity}/finalize', [DealController::class, 'finalize'])->name('opportunities.finalize');
    Route::post('deals/{deal}/revert', [DealController::class, 'revert'])->name('deals.revert');

    // Agenda (Calendário)
    Route::get('agenda', [CalendarController::class, 'index'])->name('agenda.index');
    Route::get('agenda/resources', [CalendarController::class, 'resources'])->name('agenda.resources');
    Route::get('agenda/members/{user}/services', [CalendarController::class, 'memberServices'])->name('agenda.members.services');
    Route::get('agenda/clients', [CalendarController::class, 'clients'])->name('agenda.clients');
    Route::post('agenda/clients', [CalendarController::class, 'storeClient'])->name('agenda.clients.store');
    Route::put('agenda/clients/{client}/nif', [CalendarController::class, 'updateClientNif'])->name('agenda.clients.nif');
    Route::get('agenda/clients/{client}/wallet', [CalendarController::class, 'clientWallet'])->name('agenda.clients.wallet');
    Route::get('agenda/clients/{client}/saved-cards', [CalendarController::class, 'clientSavedCards'])->name('agenda.clients.saved_cards');
    Route::get('agenda/events', [CalendarController::class, 'events'])->name('agenda.events');
    Route::get('agenda/events/{calendarEvent}', [CalendarController::class, 'show'])->name('agenda.events.show');
    Route::get('agenda/events/{calendarEvent}/activity-log', [CalendarController::class, 'activityLog'])->name('agenda.events.activity_log');
    Route::get('agenda/events/{calendarEvent}/same-day-payable', [CalendarController::class, 'sameDayPayable'])->name('agenda.events.same_day_payable');
    Route::post('agenda/events', [CalendarController::class, 'store'])->name('agenda.events.store');
    Route::put('agenda/events/{calendarEvent}', [CalendarController::class, 'update'])->name('agenda.events.update');
    Route::post('agenda/events/{calendarEvent}/update', [CalendarController::class, 'update'])->name('agenda.events.update.post');
    Route::get('agenda/events/{calendarEvent}/cancellation-preview', [CalendarController::class, 'cancellationPreview'])->name('agenda.events.cancellation_preview');
    Route::post('agenda/events/{calendarEvent}/status', [CalendarController::class, 'updateStatus'])->name('agenda.events.status');
    Route::delete('agenda/events/{calendarEvent}', [CalendarController::class, 'destroy'])->name('agenda.events.destroy');
    Route::get('agenda/events/{calendarEvent}/checkout', [CheckoutController::class, 'checkout'])->name('agenda.checkout');
    Route::get('agenda/events/{calendarEvent}/deposit', [AgendaDepositController::class, 'show'])->name('agenda.deposit.show');
    Route::middleware('cash.register.open')->group(function () {
        Route::post('agenda/events/{calendarEvent}/deposit', [AgendaDepositController::class, 'store'])->name('agenda.deposit.store');
        Route::post('agenda/events/{calendarEvent}/deposit/mbway/intent', [AgendaDepositController::class, 'createMbwayIntent'])->name('agenda.deposit.mbway.intent');
        Route::post('agenda/events/{calendarEvent}/deposit/mbway/finalize', [AgendaDepositController::class, 'finalizeMbway'])->name('agenda.deposit.mbway.finalize');
        Route::post('agenda/events/{calendarEvent}/deposit/card', [AgendaDepositController::class, 'storeCard'])->name('agenda.deposit.card');
        Route::post('agenda/checkout', [CheckoutController::class, 'store'])->name('agenda.checkout.store');
        Route::post('agenda/checkout/mbway/intent', [CheckoutController::class, 'createMbwayIntent'])->name('agenda.checkout.mbway.intent');
        Route::post('agenda/checkout/mbway/finalize', [CheckoutController::class, 'finalizeMbway'])->name('agenda.checkout.mbway.finalize');
    });
    Route::post('agenda/events/{calendarEvent}/invoices/email', [CheckoutController::class, 'sendMarcacaoInvoicesEmail'])->name('agenda.invoices.email');
    Route::get('sales/{sale}/pdf', [CheckoutController::class, 'pdf'])->name('sales.pdf');
    Route::get('sales/{sale}/vendus-pdf', [CheckoutController::class, 'vendusPdf'])->name('sales.vendus.pdf');
    Route::get('sales/{sale}/credit-note-pdf', [CheckoutController::class, 'creditNotePdf'])->name('sales.credit-note.pdf');
    Route::post('sales/{sale}/revert', [CheckoutController::class, 'revert'])->name('sales.revert');
    Route::post('sales/{sale}/finalize-invoice', [CheckoutController::class, 'finalizeInvoice'])->name('sales.finalize-invoice');

    // Serviços e Categorias
    Route::get('services', [CategoryController::class, 'index'])->name('services.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index'); // lista em JSON para AJAX (badges)
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::match(['post', 'put'], 'categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('categories/all/services', [ServiceController::class, 'allGrouped'])->name('services.allGrouped');
    Route::get('categories/{category}/services', [ServiceController::class, 'index'])->name('services.byCategory')->where('category', '[0-9]+');
    Route::get('servicos/equipa', [ServiceController::class, 'tecnicos'])->name('services.tecnicos');
    Route::post('servicos/equipa', [ServiceController::class, 'syncTecnicos'])->name('services.tecnicos.sync');
    Route::get('services/export-pdf', [ServiceController::class, 'exportPdf'])->name('services.export_pdf');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::post('services', [ServiceController::class, 'store'])->name('services.store');
    Route::match(['post', 'put'], 'services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::post('categories/{category}/services/reorder', [ServiceController::class, 'reorder'])->name('services.reorder');

    // Extras / Add-ons
    Route::get('extras', [ExtraController::class, 'index'])->name('extras.index');
    Route::get('extras/create', [ExtraController::class, 'create'])->name('extras.create');
    Route::post('extras', [ExtraController::class, 'store'])->name('extras.store');
    Route::get('extras/list', [ExtraController::class, 'list'])->name('extras.list');
    Route::get('extras/{extra}', [ExtraController::class, 'show'])->name('extras.show');
    Route::get('extras/{extra}/edit', [ExtraController::class, 'edit'])->name('extras.edit');
    Route::match(['put', 'patch'], 'extras/{extra}', [ExtraController::class, 'update'])->name('extras.update');
    Route::delete('extras/{extra}', [ExtraController::class, 'destroy'])->name('extras.destroy');
    Route::get('extra-categories/{extraCategory}', [ExtraController::class, 'showCategory'])->name('extras.categories.show');
    Route::post('extra-categories', [ExtraController::class, 'storeCategory'])->name('extras.categories.store');
    Route::match(['put', 'patch'], 'extra-categories/{extraCategory}', [ExtraController::class, 'updateCategory'])->name('extras.categories.update');
    Route::delete('extra-categories/{extraCategory}', [ExtraController::class, 'destroyCategory'])->name('extras.categories.destroy');

    Route::get('fees', [FeeController::class, 'index'])->name('fees.index');
    Route::post('fees', [FeeController::class, 'store'])->name('fees.store');
    Route::get('fees/{fee}', [FeeController::class, 'show'])->name('fees.show');
    Route::match(['put', 'patch'], 'fees/{fee}', [FeeController::class, 'update'])->name('fees.update');
    Route::delete('fees/{fee}', [FeeController::class, 'destroy'])->name('fees.destroy');

    Route::prefix('marketing')->name('marketing.')->group(function () {
        Route::get('campanhas-sms', [MarketingSmsController::class, 'index'])->name('campanhas-sms');
        Route::post('campanhas-sms/enviar', [MarketingSmsController::class, 'send'])
            ->middleware('throttle:20,1')
            ->name('campanhas-sms.send');
    });

    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('marcacoes/export', [RelatoriosController::class, 'marcacoesExport'])->name('marcacoes.export');
        Route::get('marcacoes/pdf', [RelatoriosController::class, 'marcacoesPdf'])->name('marcacoes.pdf');
        Route::get('marcacoes', [RelatoriosController::class, 'marcacoes'])->name('marcacoes');
        Route::get('vendas/export', [RelatoriosController::class, 'vendasExport'])->name('vendas.export');
        Route::get('vendas/pdf', [RelatoriosController::class, 'vendasPdf'])->name('vendas.pdf');
        Route::get('vendas', [RelatoriosController::class, 'vendas'])->name('vendas');
        Route::get('comissoes/export', [RelatoriosController::class, 'comissoesExport'])->name('comissoes.export');
        Route::get('comissoes/pdf', [RelatoriosController::class, 'comissoesPdf'])->name('comissoes.pdf');
        Route::get('comissoes', [RelatoriosController::class, 'comissoes'])->name('comissoes');
        Route::get('sms', [RelatoriosController::class, 'sms'])->name('sms');
        Route::get('booking-funil', [RelatoriosController::class, 'bookingFunnel'])->name('booking-funnel');
        Route::get('caixa', [CashRegisterController::class, 'index'])->name('caixa');
    });

    Route::prefix('caixa')->name('caixa.')->group(function () {
        Route::get('/', fn () => redirect()->route('relatorios.caixa'));
        Route::get('/close', fn () => redirect()->route('relatorios.caixa'));
        Route::get('/close-summary', [CashRegisterController::class, 'closeSummary'])->name('close.summary');
        Route::get('/open-preview', [CashRegisterController::class, 'openPreview'])->name('open.preview');
        Route::post('/open', [CashRegisterController::class, 'open'])->name('open');
        Route::post('/close', [CashRegisterController::class, 'close'])->name('close.store');
    });

    // Rotas do template (protegidas)
    Route::get('{page}', [DashboardController::class, 'page'])->where('page', '[A-Za-z0-9\-]+');
});
