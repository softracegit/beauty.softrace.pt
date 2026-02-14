<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Proposal;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    /**
     * Store a new proposal
     */
    public function store(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'proposed_value' => ['required', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'string'],
        ]);

        if (!$opportunity->properties()->where('properties.id', $validated['property_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'O imóvel não está associado a esta oportunidade.',
            ], 422);
        }

        $proposal = $opportunity->proposals()->create([
            'property_id' => $validated['property_id'],
            'proposed_value' => $validated['proposed_value'],
            'conditions' => $validated['conditions'] ?? null,
            'status' => Proposal::STATUS_RASCUNHO,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proposta criada com sucesso.',
            'proposal' => $proposal->load('property.mainImage'),
        ]);
    }

    /**
     * Update a proposal
     */
    public function update(Request $request, Proposal $proposal)
    {
        $validated = $request->validate([
            'proposed_value' => ['sometimes', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:rascunho,enviada'],
        ]);

        if (in_array($proposal->status, [Proposal::STATUS_APROVADA, Proposal::STATUS_REJEITADA])) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível editar uma proposta aprovada ou rejeitada.',
            ], 422);
        }

        $proposal->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proposta atualizada com sucesso.',
            'proposal' => $proposal->fresh()->load(['property.mainImage', 'counterProposals']),
        ]);
    }

    /**
     * Approve a proposal
     */
    public function approve(Proposal $proposal)
    {
        if ($proposal->status !== Proposal::STATUS_ENVIADA) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas propostas enviadas podem ser aprovadas.',
            ], 422);
        }

        $proposal->update([
            'status' => Proposal::STATUS_APROVADA,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        // Atualizar estado da oportunidade para "Proposta Aceite"
        $opportunity = $proposal->opportunity;
        if ($opportunity->status !== \App\Models\Opportunity::STATUS_PROPOSTA_ACEITE) {
            $opportunity->updateStatus(
                \App\Models\Opportunity::STATUS_PROPOSTA_ACEITE,
                auth()->id(),
                'Proposta aprovada (imóvel: ' . $proposal->property->title . ')'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Proposta aprovada com sucesso.',
            'proposal' => $proposal->fresh()->load(['property.mainImage', 'counterProposals']),
        ]);
    }

    /**
     * Reject a proposal
     */
    public function reject(Request $request, Proposal $proposal)
    {
        if ($proposal->status !== Proposal::STATUS_ENVIADA) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas propostas enviadas podem ser rejeitadas.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $proposal->update([
            'status' => Proposal::STATUS_REJEITADA,
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proposta rejeitada.',
            'proposal' => $proposal->fresh()->load(['property.mainImage', 'counterProposals']),
        ]);
    }

    /**
     * Create a counter-proposal
     */
    public function storeCounterProposal(Request $request, Proposal $proposal)
    {
        if ($proposal->status !== Proposal::STATUS_REJEITADA) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas propostas rejeitadas podem ter contrapropostas.',
            ], 422);
        }

        $validated = $request->validate([
            'proposed_value' => ['required', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'string'],
        ]);

        $counterProposal = $proposal->opportunity->proposals()->create([
            'property_id' => $proposal->property_id,
            'parent_proposal_id' => $proposal->id,
            'proposed_value' => $validated['proposed_value'],
            'conditions' => $validated['conditions'] ?? null,
            'status' => Proposal::STATUS_RASCUNHO,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraproposta criada com sucesso.',
            'proposal' => $counterProposal->load(['property.mainImage', 'parentProposal']),
        ]);
    }
}
