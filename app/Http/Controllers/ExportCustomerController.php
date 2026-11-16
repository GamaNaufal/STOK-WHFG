<?php

namespace App\Http\Controllers;

use App\Models\ExportCustomer;
use App\Services\{ExportCustomerService, ExportCustomerStockPolicy};
use Illuminate\Http\Request;

class ExportCustomerController extends Controller
{
    public function index(Request $request)
    {
        $ready = app(ExportCustomerStockPolicy::class)->ready();
        $q = trim($request->validate(['q' => 'nullable|string|max:100'])['q'] ?? '');
        $customers = $ready ? ExportCustomer::with('aliases')->when($q !== '', fn ($query) => $query->where(fn ($r) =>
            $r->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%')->orWhereHas('aliases', fn ($a) => $a->where('label', 'like', '%'.$q.'%'))))
            ->orderBy('name')->paginate(20)->withQueryString() : null;
        return view('export.customers.index', compact('customers', 'q', 'ready'));
    }

    public function create() { abort_unless(app(ExportCustomerStockPolicy::class)->ready(), 503); return view('export.customers.form', ['customer' => null]); }
    public function edit(ExportCustomer $customer) { return view('export.customers.form', compact('customer')); }

    private function data(Request $request, bool $edit): array
    {
        return $request->validate(['code' => 'required|string|max:100', 'name' => 'required|string|max:255',
            'aliases' => 'nullable|string|max:20000', 'is_active' => 'nullable|boolean', 'revision' => ($edit ? 'required' : 'nullable').'|integer|min:1']);
    }
    public function store(Request $request, ExportCustomerService $service)
    {
        $service->save($this->data($request, false), $request->user());
        return redirect()->route('export.customers.index')->with('success', 'Customer ditambahkan.');
    }
    public function update(Request $request, ExportCustomer $customer, ExportCustomerService $service)
    {
        $service->save($this->data($request, true), $request->user(), $customer->id);
        return redirect()->route('export.customers.index')->with('success', 'Master customer diperbarui, snapshot delivery lama tetap.');
    }
    public function options(Request $request, ExportCustomerService $service)
    {
        $data = $request->validate(['q' => 'nullable|string|max:255', 'code' => 'nullable|string|max:100']);
        return response()->json($service->resolve($data['q'] ?? '', $data['code'] ?? ''))->header('Cache-Control', 'private, no-store');
    }
}
