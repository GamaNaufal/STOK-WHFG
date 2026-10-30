<?php

namespace App\Http\Controllers;

use App\Models\{ExportDeliveryPlan, ExportPalletPlan};

class ExportPlanPrintController extends Controller
{
    private function finalized(ExportDeliveryPlan $delivery): ExportPalletPlan
    {
        $delivery->load('palletPlan.finalizedBy');
        abort_unless($delivery->palletPlan?->isFinalFor($delivery), 409,
            'Cetak tersedia setelah perencanaan pallet difinalisasi sesuai revisi delivery terbaru.');
        return $delivery->palletPlan;
    }

    public function delivery(ExportDeliveryPlan $deliveryPlan)
    {
        $planning = $this->finalized($deliveryPlan);
        $deliveryPlan->load('items');
        $planning->loadCount('pallets');
        return response()->view('export.print.delivery', [
            'delivery' => $deliveryPlan, 'planning' => $planning, 'printedAt' => now(),
            'returnUrl' => route('export.delivery-plans.show', $deliveryPlan),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function pallets(ExportDeliveryPlan $deliveryPlan, ?int $pallet = null)
    {
        $planning = $this->finalized($deliveryPlan);
        $pallets = $planning->pallets()->with('items')->when($pallet !== null, fn ($q) => $q->whereKey($pallet))->get();
        abort_if($pallets->isEmpty(), 404);
        return response()->view('export.print.pallets', [
            'delivery' => $deliveryPlan, 'planning' => $planning, 'pallets' => $pallets, 'printedAt' => now(),
            'returnUrl' => route('export.pallet-plans.show', $deliveryPlan),
        ])->header('Cache-Control', 'private, no-store');
    }
}
