<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Property;
use App\Models\Deal;

class DashboardController extends Controller
{
    /**
     * Dashboard principal - Resumo Geral
     */
    public function index()
    {
        return view('dashboard.index');
    }

    /**
     * Dashboard de Imóveis
     */
    public function imoveis()
    {
        return view('dashboard.imoveis');
    }

    /**
     * Dashboard de Negócios
     */
    public function negocios()
    {
        return view('dashboard.negocios');
    }

    /**
     * Dashboard de Clientes
     */
    public function clientes()
    {
        return view('dashboard.clientes');
    }

    /**
     * Método antigo para páginas do template (mantido para compatibilidade)
     */
    public function page($page)
    {
        $allowedPages = [
            'index',      
            'apps-calendar',
            'apps-chat',
            'apps-email',
            'apps-hr-add-leave',
            'apps-hr-attendance',
            'apps-hr-employee-leave',
            'apps-hr-employee-list',
            'apps-hr-holidays',
            'apps-hr-leave',
            'apps-hr-main-attendance',
            'apps-hr-payroll-employee-salary',
            'apps-hr-payroll-payslip',
            'apps-hr-performance',
            'apps-kanban',
            'apps-prodcast-audience-analytics',
            'apps-prodcast-episode-manage',
            'apps-prodcast-list',
            'apps-real-estate-add-property',
            'apps-real-estate-agents',
            'apps-real-estate-clinets',
            'apps-real-estate-property-details',
            'apps-real-estate-property-list',
            'auth-email-verify',
            'auth-forgot-password',
            'auth-reset-password',
            'auth-signin',
            'auth-signout',
            'auth-signup',
            'auth-two-step-verify',
            'chart-apex-line',
            'chart-js-chart',
            'coming-soon',
            'dashboard-fitness',
            'dashboard-prodcast',
            'dashboard-real-estate',
            'echart-chart',
            'error',
            'google-maps',
            'icons-bootstrap',
            'icons-lucide',
            'icons-remix',
            'maps-leaflet',
            'maps-vector',
            'not-authorize',
            'pages-billing-subscription',
            'pages-blog-create',
            'pages-blog-details',
            'pages-blog-list',          
            'pages-faqs',       
            'pages-pricing',
            'pages-privacy-policy',
            'pages-profile',
            'pages-starter',
            'pages-terms-conditions',
            'pages-timeline',
            'ui-accordions',
            'ui-advance-swiper',
            'ui-alerts',
            'ui-avatars',
            'ui-badges',
            'ui-block',
            'ui-breadcrumbs',
            'ui-button-group',
            'ui-buttons',
            'ui-card',
            'ui-carousel',
            'ui-cookie',
            'ui-date-picker',
            'ui-draggable-cards',
            'ui-dropdowns',
            'ui-floating-labels',
            'ui-form-advanced',
            'ui-form-checkboxs-radios',
            'ui-form-editor',
            'ui-form-elements',
            'ui-form-file-uploads',
            'ui-form-input-group',
            'ui-form-input-masks',
            'ui-form-input-spin',
            'ui-form-layout',
            'ui-form-range',
            'ui-form-select',
            'ui-form-validation',
            'ui-form-wizards',
            'ui-images-figures',
            'ui-links',
            'ui-list',
            'ui-media-player',
            'ui-modal',
            'ui-offcanvas',
            'ui-pagination',
            'ui-placeholders',
            'ui-popover',
            'ui-progress',
            'ui-ratings',
            'ui-ribbons',
            'ui-scrollspy',
            'ui-separator',
            'ui-sortable-js',
            'ui-spinner',
            'ui-sweetalert2',
            'ui-tables-basic',
            'ui-tables-datatables',
            'ui-tables-gridjs',
            'ui-tables-listjs',
            'ui-tabs',
            'ui-tagify',
            'ui-toast',
            'ui-tooltips',
            'ui-tour',
            'ui-treeview',
            'ui-typography',
            'ui-utilities',
            'under-maintenance'
        ];

        if (in_array($page, $allowedPages) && view()->exists($page)) {
            return view($page);
        }

        abort(404);
    }
}
