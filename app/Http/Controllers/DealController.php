<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Deal;
use App\Models\DealAgentCommission;
use App\Models\Opportunity;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealController extends Controller
{
    /**
     * Display a listing of deals.
     */
    public function index()
    {
        $deals = Deal::with([
            'opportunity',
            'property',
            'client',
            'agentCommissions.agent',
            'closedBy',
        ])
            ->orderBy('closed_at', 'desc')
            ->paginate(15);

        return view('deals.index', compact('deals'));
    }

    /**
     * Display the specified deal.
     */
    public function show(Deal $deal)
    {
        $deal->load([
            'opportunity',
            'property.mainImage',
            'client',
            'proposal',
            'agentCommissions.agent',
            'closedBy',
            'revertedBy',
        ]);

        return view('deals.show', compact('deal'));
    }

    /**
     * Finalize opportunity and create deal.
     */
    public function finalize(Request $request, Opportunity $opportunity)
    {
        // Validate that opportunity can be finalized
        if (!$opportunity->canBeFinalized()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oportunidade não pode ser finalizada. Verifique se existe exatamente uma proposta aprovada.',
            ], 422);
        }

        // Validate request
        $validated = $request->validate([
            'property_commission_value' => 'nullable|numeric|min:0',
            'property_commission_percentage' => 'nullable|numeric|min:0|max:100',
            'agent_commissions' => 'required|array|min:1',
            'agent_commissions.*.agent_id' => 'required|exists:agents,id',
            'agent_commissions.*.role' => 'required|in:' . implode(',', array_keys(Deal::agentRoles())),
            'agent_commissions.*.commission_value' => 'nullable|numeric|min:0',
            'agent_commissions.*.commission_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ], [
            'agent_commissions.required' => 'É necessário definir pelo menos um agente.',
            'agent_commissions.min' => 'É necessário definir pelo menos um agente.',
            'agent_commissions.*.agent_id.required' => 'Selecione um agente.',
            'agent_commissions.*.agent_id.exists' => 'Agente inválido.',
            'agent_commissions.*.role.required' => 'Selecione o papel do agente.',
            'agent_commissions.*.role.in' => 'Papel do agente inválido.',
        ]);

        // Get approved proposal
        $approvedProposal = $opportunity->approved_proposal;
        if (!$approvedProposal) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi encontrada uma proposta aprovada.',
            ], 422);
        }

        $property = $approvedProposal->property;
        $saleValue = (float) $approvedProposal->proposed_value;

        // Calculate property commission (use form values or fallback to property)
        $propertyCommissionValue = isset($validated['property_commission_value']) && $validated['property_commission_value'] !== ''
            ? (float) $validated['property_commission_value']
            : null;
        $propertyCommissionPercentage = isset($validated['property_commission_percentage']) && $validated['property_commission_percentage'] !== ''
            ? (float) $validated['property_commission_percentage']
            : null;

        // If no value provided, calculate from percentage
        if ($propertyCommissionValue === null && $propertyCommissionPercentage !== null) {
            $propertyCommissionValue = round($saleValue * ($propertyCommissionPercentage / 100), 2);
        } elseif ($propertyCommissionValue === null) {
            $propertyCommissionValue = (float) ($property->commission_value ?? 0);
            if ($propertyCommissionValue == 0 && $property->commission_percentage) {
                $propertyCommissionValue = round($saleValue * ((float) $property->commission_percentage / 100), 2);
            }
            $propertyCommissionPercentage = $propertyCommissionValue > 0 && $saleValue > 0
                ? round(($propertyCommissionValue / $saleValue) * 100, 2) : $property->commission_percentage;
        }

        $maxCommission = $propertyCommissionValue;

        // Validate: total agent commissions cannot exceed max commission
        $totalAgentCommissions = 0;
        foreach ($validated['agent_commissions'] as $ac) {
            $val = isset($ac['commission_value']) && $ac['commission_value'] !== '' ? (float) $ac['commission_value'] : 0;
            if ($val == 0 && isset($ac['commission_percentage']) && $ac['commission_percentage'] !== '') {
                $val = round($maxCommission * ((float) $ac['commission_percentage'] / 100), 2);
            }
            $totalAgentCommissions += $val;
        }

        if ($totalAgentCommissions > $maxCommission) {
            return response()->json([
                'success' => false,
                'message' => 'O total das comissões dos consultores (' . number_format($totalAgentCommissions, 2, ',', '.') . ' €) não pode ultrapassar a comissão total do imóvel (' . number_format($maxCommission, 2, ',', '.') . ' €).',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create the deal (snapshot)
            $deal = Deal::create([
                'reference' => Deal::generateReference(),
                'opportunity_id' => $opportunity->id,
                'proposal_id' => $approvedProposal->id,
                'property_id' => $property->id,
                'client_id' => $opportunity->client_id,
                'property_reference' => $property->reference,
                'property_title' => $property->title,
                'property_address' => $property->full_address,
                'transaction_type' => $property->transactionType?->name ?? 'N/A',
                'final_price' => $saleValue,
                'property_commission_value' => $propertyCommissionValue,
                'property_commission_percentage' => $propertyCommissionPercentage,
                'status' => Deal::STATUS_FECHADO,
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create agent commissions
            foreach ($validated['agent_commissions'] as $agentData) {
                $agent = Agent::find($agentData['agent_id']);
                
                DealAgentCommission::create([
                    'deal_id' => $deal->id,
                    'agent_id' => $agent->id,
                    'role' => $agentData['role'],
                    'agent_name' => $agent->name,
                    'agent_email' => $agent->email,
                    'commission_value' => $agentData['commission_value'] ?? null,
                    'commission_percentage' => $agentData['commission_percentage'] ?? null,
                ]);
            }

            // Update opportunity status to "Ganha"
            $opportunity->updateStatus(
                Opportunity::STATUS_GANHA,
                auth()->id(),
                'Negócio fechado - ' . $deal->reference
            );

            // Update property status based on transaction type
            $newPropertyStatus = $this->getPropertyStatusForTransactionType($property);
            $property->update(['status' => $newPropertyStatus]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Negócio fechado com sucesso!',
                'deal' => $deal->load('agentCommissions.agent'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fechar negócio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revert a deal (high permission required - not implemented yet).
     */
    public function revert(Request $request, Deal $deal)
    {
        // Check if deal can be reverted
        if (!$deal->canBeReverted()) {
            return response()->json([
                'success' => false,
                'message' => 'Este negócio não pode ser revertido.',
            ], 422);
        }

        $validated = $request->validate([
            'reversion_reason' => 'required|string|max:1000',
        ], [
            'reversion_reason.required' => 'É necessário indicar o motivo da reversão.',
        ]);

        try {
            DB::beginTransaction();

            // Update deal status
            $deal->update([
                'status' => Deal::STATUS_REVERTIDO,
                'reverted_at' => now(),
                'reverted_by' => auth()->id(),
                'reversion_reason' => $validated['reversion_reason'],
            ]);

            // Revert opportunity status to "proposta_aceite"
            $deal->opportunity->updateStatus(
                Opportunity::STATUS_PROPOSTA_ACEITE,
                auth()->id(),
                'Negócio revertido - ' . $validated['reversion_reason']
            );

            // Revert property status to "disponivel"
            $deal->property->update(['status' => Property::STATUS_DISPONIVEL]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Negócio revertido com sucesso.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao reverter negócio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get property status based on transaction type.
     */
    private function getPropertyStatusForTransactionType(Property $property): string
    {
        // Get transaction type name
        $transactionTypeName = strtolower($property->transactionType?->name ?? '');

        // If it's rental/arrendamento, set to "arrendado"
        if (str_contains($transactionTypeName, 'arrend') || str_contains($transactionTypeName, 'alug')) {
            return Property::STATUS_ARRENDADO;
        }

        // Default to "vendido" for sales
        return Property::STATUS_VENDIDO;
    }
}
