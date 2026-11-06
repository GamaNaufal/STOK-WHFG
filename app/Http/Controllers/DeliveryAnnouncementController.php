<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAnnouncement;
use App\Models\DeliveryOrder;
use App\Models\ExportDeliveryPlan;
use App\Models\PartSetting;
use App\Services\DeliveryAnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = DeliveryAnnouncement::with('targets')->latest('id')->paginate(16);

        return view('admin.delivery-announcements.index', compact('announcements'));
    }

    public function create()
    {
        $parts = PartSetting::orderBy('part_number')->pluck('part_number');

        $customers = DeliveryOrder::latest('id')->limit(100)->pluck('customer_name')
            ->concat(ExportDeliveryPlan::latest('id')->limit(100)->pluck('customer_name'))
            ->unique(fn ($name) => DeliveryAnnouncementService::normalize($name))->sort(SORT_NATURAL | SORT_FLAG_CASE);

        return view('admin.delivery-announcements.form', ['announcement' => null, 'parts' => $parts, 'customers' => $customers]);
    }

    public function store(Request $request, DeliveryAnnouncementService $service)
    {
        $data = $request->validate($this->contentRules() + [
            'customer_name' => 'required|string|max:255',
            'part_number' => ['required', 'string', 'max:100', Rule::exists('part_settings', 'part_number')],
            'quota_type' => 'required|in:box,delivery',
        ], [], $this->labels());

        $part = PartSetting::where('part_number', $data['part_number'])->value('part_number');
        $data['part_number'] = $part;
        $announcement = $service->create($data, $request->user());

        return redirect()->route('delivery-announcements.show', $announcement)->with('success', 'Pengumuman diterbitkan.');
    }

    public function show(DeliveryAnnouncement $announcement)
    {
        $announcement->load('targets');
        $history = DB::table('delivery_announcement_impressions as impression')
            ->join('delivery_announcement_targets as target', 'target.id', '=', 'impression.target_id')
            ->where('target.announcement_id', $announcement->id)
            ->select('impression.*', 'target.scope')->orderByDesc('impression.last_seen_at')
            ->orderByDesc('impression.id')->paginate(20);

        return view('admin.delivery-announcements.show', compact('announcement', 'history'));
    }

    public function edit(DeliveryAnnouncement $announcement)
    {
        return view('admin.delivery-announcements.form', compact('announcement'));
    }

    public function update(Request $request, DeliveryAnnouncement $announcement, DeliveryAnnouncementService $service)
    {
        $data = $request->validate($this->contentRules(), [], $this->labels());
        $service->update($announcement->id, $data, $request->user());

        return redirect()->route('delivery-announcements.show', $announcement)->with('success', 'Pengumuman diperbarui.');
    }

    public function status(Request $request, DeliveryAnnouncement $announcement, DeliveryAnnouncementService $service)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $service->update($announcement->id, ['enabled' => (bool) $data['enabled']], $request->user());

        return redirect()->route('delivery-announcements.show', $announcement)
            ->with('success', $data['enabled'] ? 'Pengumuman diaktifkan.' : 'Pengumuman dinonaktifkan.');
    }

    private function contentRules(): array
    {
        return ['message' => 'required|string|max:2000', 'quota' => 'required|integer|min:1|max:1000000'];
    }

    private function labels(): array
    {
        return ['message' => 'isi pengumuman', 'quota' => 'jumlah kuota', 'customer_name' => 'nama customer',
            'part_number' => 'nomor part', 'quota_type' => 'jenis kuota'];
    }
}
