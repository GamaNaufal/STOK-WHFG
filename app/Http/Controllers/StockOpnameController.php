<?php

namespace App\Http\Controllers;

use App\Models\StockOpnameSession;
use App\Services\StockOpnameService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StockOpnameController extends Controller
{
    private function context(Request $request, ?StockOpnameSession $opname = null): array
    {
        $scope = $request->route('scope');
        abort_unless(in_array($scope, ['domestic', 'export'], true), 404);
        if ($opname) abort_unless($opname->scope === $scope, 404);
        $base = $scope === 'export' ? 'export.stock-opname' : 'stock-opname';
        $canManage = $request->user()->canAccess($scope === 'export' ? 'stock_opname_export_manage' : 'stock_opname_local_manage');
        return ['scope' => $scope, 'scopeLabel' => $scope === 'export' ? 'Ekspor' : 'Lokal', 'base' => $base, 'canManage' => $canManage];
    }

    public function index(Request $request, StockOpnameService $service)
    {
        $context = $this->context($request);
        $active = StockOpnameSession::where('active_scope', $context['scope'])->with('starter')->first();
        $history = StockOpnameSession::where('scope', $context['scope'])->where('status', 'completed')
            ->with(['starter', 'finisher'])->latest('id')->paginate(20);
        return response()->view('stock-opname.index', $context + [
            'active' => $active, 'history' => $history, 'startToken' => (string) Str::uuid(),
            'activeSummary' => $active ? $service->summary($active) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function start(Request $request, StockOpnameService $service)
    {
        $context = $this->context($request);
        $data = $request->validate(['request_token' => 'required|uuid']);
        $session = $service->start($context['scope'], $data['request_token'], $request->user());
        return redirect()->route($context['base'].'.show', $session);
    }

    private function details(Request $request, StockOpnameSession $opname, StockOpnameService $service): array
    {
        $data = $this->filters($request);
        $tab = $data['tab'] ?? ($opname->status === 'completed' ? 'missing' : 'scans');
        $search = trim($data['q'] ?? '');
        $scanTable = in_array($tab, ['scans', 'ng'], true);
        if ($scanTable) {
            $query = $opname->scans()->with('scanner')->when($tab === 'ng', fn ($q) => $q->where('outcome', 'ng'))
                ->when($search !== '', fn ($q) => $q->where('input', 'like', '%'.$search.'%'))->latest('id');
        } else {
            $query = $opname->items()->with('scanner')
                ->when($tab === 'missing', fn ($q) => $q->where('present', true)->whereNull('first_scanned_at'))
                ->when($tab === 'found', fn ($q) => $q->where('present', true)->whereNotNull('first_scanned_at'))
                ->when($tab === 'changes', fn ($q) => $q->where('change_kind', '!=', 'unchanged'))
                ->when($search !== '', fn ($q) => $q->where('box_number', 'like', '%'.$search.'%'))->orderBy('id');
        }
        $rows = $query->paginate(20)->appends(['tab' => $tab, 'q' => $search]);
        return ['opname' => $opname, 'summary' => $service->summary($opname),
            'tab' => $tab, 'search' => $search, 'scanTable' => $scanTable, 'rows' => $rows];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'tab' => ['nullable', Rule::in(['scans', 'ng', 'missing', 'found', 'changes', 'all'])],
            'q' => 'nullable|string|max:100',
        ]);
    }

    public function show(Request $request, StockOpnameSession $opname, StockOpnameService $service)
    {
        $data = $this->context($request, $opname) + $this->details($request, $opname, $service);
        if ($request->expectsJson()) return response()->json([
            'html' => view('stock-opname.results', $data)->render(), 'summary' => $data['summary'],
        ])->header('Cache-Control', 'private, no-store');
        $opname->load(['starter', 'finisher']);
        return response()->view('stock-opname.show', $data)->header('Cache-Control', 'private, no-store');
    }

    public function scan(Request $request, StockOpnameSession $opname, StockOpnameService $service)
    {
        $context = $this->context($request, $opname);
        $data = $request->validate(['box_number' => 'required|string|max:256', 'request_token' => 'required|uuid']);
        $this->filters($request);
        $scan = $service->scan($opname->id, trim($data['box_number']), $data['request_token'], $request->user());
        if (!$request->expectsJson()) return redirect()->route($context['base'].'.show', $opname)
            ->with('success', $scan->input.' · '.$scan->reason);
        $details = $this->details($request, $opname->fresh(), $service);
        return response()->json([
            'outcome' => $scan->outcome, 'box_number' => $scan->input, 'message' => $scan->reason,
            'snapshot' => $scan->snapshot, 'summary' => $details['summary'],
            'html' => view('stock-opname.results', $context + $details)->render(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function refresh(Request $request, StockOpnameSession $opname, StockOpnameService $service)
    {
        $context = $this->context($request, $opname);
        $service->refresh($opname->id);
        return redirect()->route($context['base'].'.show', ['opname' => $opname->id, 'tab' => 'changes']);
    }

    public function finish(Request $request, StockOpnameSession $opname, StockOpnameService $service)
    {
        $context = $this->context($request, $opname);
        $request->validate(['confirmed' => 'accepted']);
        $service->finish($opname->id, $request->user());
        return redirect()->route($context['base'].'.show', $opname);
    }
}
