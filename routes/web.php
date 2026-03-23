<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ExtraController;
use App\Http\Controllers\ActivityLogController;

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

// Rotas protegidas (requerem autenticação e agent associado)
Route::middleware(['auth', 'has.agent'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/imoveis', [DashboardController::class, 'imoveis'])->name('dashboard.imoveis');
    Route::get('/dashboard/negocios', [DashboardController::class, 'negocios'])->name('dashboard.negocios');
    Route::get('/dashboard/clientes', [DashboardController::class, 'clientes'])->name('dashboard.clientes');
    Route::get('/dashboard/ocupacao', [DashboardController::class, 'ocupacao'])->name('dashboard.ocupacao');

    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

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
    Route::get('agenda/events', [CalendarController::class, 'events'])->name('agenda.events');
    Route::get('agenda/events/{calendarEvent}', [CalendarController::class, 'show'])->name('agenda.events.show');
    Route::post('agenda/events', [CalendarController::class, 'store'])->name('agenda.events.store');
    Route::put('agenda/events/{calendarEvent}', [CalendarController::class, 'update'])->name('agenda.events.update');
    Route::post('agenda/events/{calendarEvent}/update', [CalendarController::class, 'update'])->name('agenda.events.update.post');
    Route::post('agenda/events/{calendarEvent}/status', [CalendarController::class, 'updateStatus'])->name('agenda.events.status');
Route::delete('agenda/events/{calendarEvent}', [CalendarController::class, 'destroy'])->name('agenda.events.destroy');
    Route::get('agenda/events/{calendarEvent}/checkout', [CheckoutController::class, 'checkout'])->name('agenda.checkout');
    Route::post('agenda/checkout', [CheckoutController::class, 'store'])->name('agenda.checkout.store');
    Route::get('sales/{sale}/pdf', [CheckoutController::class, 'pdf'])->name('sales.pdf');
    Route::post('sales/{sale}/revert', [CheckoutController::class, 'revert'])->name('sales.revert');

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
    
    // Rotas do template (protegidas)
    Route::get('{page}', [DashboardController::class, 'page'])->where('page', '[A-Za-z0-9\-]+');
});