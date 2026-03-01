@extends('shared.layouts.app')

@section('title', 'Lihat Stok - Warehouse FG Yamato')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('shared.stock-view.partials.styles')
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    border-radius: 12px; 
                    box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;">
            <div>
                <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                    <i class="bi bi-eye"></i> Lihat Stok Tersedia
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Pantau ketersediaan stok produk di gudang dengan detail lengkap
                </p>
            </div>
            <div>
                <form method="GET" class="d-flex gap-2">
                    @if($viewMode === 'part' && $groupedByPart->count() > 0)
                        <a href="{{ route('stock-view.export-part', request()->query()) }}" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px; font-weight: 600; text-decoration: none;">
                            <i class="bi bi-download"></i> Export Excel
                        </a>
                    @elseif($viewMode === 'pallet' && $groupedByPallet->count() > 0)
                        <a href="{{ route('stock-view.export-pallet', request()->query()) }}" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px; font-weight: 600; text-decoration: none;">
                            <i class="bi bi-download"></i> Export Excel
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="position-relative">
            <form method="GET" action="{{ route('stock-view.index') }}" id="searchForm" class="input-group input-group-lg">
                <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                <span class="input-group-text border-0" style="background: #0C7779; color: white; border-radius: 10px 0 0 10px; font-size: 1.2rem;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" name="search" id="searchInput" class="form-control form-control-lg border-0" 
                       placeholder="{{ $viewMode === 'pallet' ? 'Cari berdasarkan No Pallet...' : 'Cari berdasarkan No Part...' }}" 
                       value="{{ $search ?? '' }}"
                       autocomplete="off"
                       style="font-size: 1rem; padding: 14px 16px;">
                <button class="btn btn-outline-secondary border-0" type="submit" style="border-radius: 0 10px 10px 0; background: white; color: #0C7779; font-weight: 600;">
                    <i class="bi bi-search"></i> Cari
                </button>
                @if($search)
                    <a href="{{ route('stock-view.index', ['view_mode' => $viewMode]) }}" class="btn btn-outline-danger border-0" style="border-radius: 0 10px 10px 0; background: white;">
                        <i class="bi bi-x-circle"></i> Atur Ulang
                    </a>
                @endif
            </form>
            <div id="searchDropdown" class="search-dropdown dropdown-list border-0 bg-white rounded-3 mt-2" 
                 style="display: none; max-height: 300px; overflow-y: auto; position: absolute; width: 100%; z-index: 1000; box-shadow: 0 8px 16px rgba(0,0,0,0.12); border: 1px solid #e5e7eb;">
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Part Numbers</p>
                        <h2 class="fw-bold" style="color: #0C7779; margin: 0; font-size: 2.5rem;">{{ $summaryTotalParts }}</h2>
                    </div>
                    <i class="bi bi-tag" style="font-size: 2.5rem; color: #0C7779; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Pallet</p>
                        <h2 class="fw-bold" style="color: #005461; margin: 0; font-size: 2.5rem;">{{ $totalPallets }}</h2>
                    </div>
                    <i class="bi bi-layers" style="font-size: 2.5rem; color: #005461; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Box</p>
                        <h2 class="fw-bold" style="color: #249E94; margin: 0; font-size: 2.5rem;">{{ (int) $summaryTotalBox }}</h2>
                    </div>
                    <i class="bi bi-box2" style="font-size: 2.5rem; color: #249E94; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total PCS</p>
                        <h2 class="fw-bold" style="color: #3BC1A8; margin: 0; font-size: 2.5rem;">{{ $summaryTotalPcs }}</h2>
                    </div>
                    <i class="bi bi-stack" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-end">
        <div class="btn-group shadow-sm" style="border-radius: 8px;">
            <a href="{{ route('stock-view.index', ['view_mode' => 'part', 'search' => $search]) }}" 
               class="btn {{ $viewMode === 'part' ? 'btn-primary' : 'btn-light' }}"
               @if($viewMode === 'part') style="background: #0C7779; border-color: #0C7779;" @endif>
                <i class="bi bi-tag"></i> By Part Number
            </a>
            <a href="{{ route('stock-view.index', ['view_mode' => 'pallet', 'search' => $search]) }}" 
               class="btn {{ $viewMode === 'pallet' ? 'btn-primary' : 'btn-light' }}"
               @if($viewMode === 'pallet') style="background: #0C7779; border-color: #0C7779;" @endif>
                <i class="bi bi-layers"></i> By Pallet
            </a>
            <a href="{{ route('stock-view.index', ['view_mode' => 'not_full', 'search' => $search]) }}" 
               class="btn {{ $viewMode === 'not_full' ? 'btn-primary' : 'btn-light' }}"
               @if($viewMode === 'not_full') style="background: #0C7779; border-color: #0C7779;" @endif>
                <i class="bi bi-exclamation-circle"></i> By Not Full
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @if(($viewMode === 'part' && $groupedByPart->count() > 0) || ($viewMode === 'pallet' && $groupedByPallet->count() > 0) || ($viewMode === 'not_full' && $notFullBoxes->count() > 0))
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                            color: white; 
                            padding: 20px 24px; 
                            font-weight: 600;
                            font-size: 15px;">
                    <i class="bi bi-table"></i> Daftar Stok Tersedia
                </div>

                <div class="table-responsive" style="overflow: hidden;">
                    <table class="table table-hover mb-0" style="margin-bottom: 0;">
                        <thead style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <tr>
                                @if($viewMode === 'part')
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-tag"></i> No Part
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                        <i class="bi bi-box2"></i> Total Box
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                        <i class="bi bi-stack"></i> Total PCS
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">Aksi</th>
                                @elseif($viewMode === 'pallet')
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-layers"></i> No Pallet
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-geo-alt"></i> Lokasi
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                        <i class="bi bi-box2"></i> Total Box
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                        <i class="bi bi-stack"></i> Total PCS
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">Aksi</th>
                                @else
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-upc-scan"></i> ID Box
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-tag"></i> No Part
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-layers"></i> No Pallet
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                        <i class="bi bi-stack"></i> PCS
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-geo-alt"></i> Lokasi
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                        <i class="bi bi-calendar"></i> Tanggal
                                    </th>
                                    <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @if($viewMode === 'part')
                                @foreach($groupedByPart as $partData)
                                    <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;" onmouseenter="this.style.backgroundColor='#f9fafb';" onmouseleave="this.style.backgroundColor='transparent';">
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 600;">
                                            <span style="background: linear-gradient(135deg, #f0f4f8 0%, #e0f2fe 100%); color: #0C7779; padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; display: inline-block; box-shadow: 0 2px 4px rgba(12, 119, 121, 0.1);">
                                                {{ $partData['part_number'] }}
                                            </span>
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 700; text-align: center; font-size: 15px;">
                                            <span style="background: #f0f4f8; padding: 6px 12px; border-radius: 8px; display: inline-block;">{{ (int) $partData['total_box'] }}</span>
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 700; text-align: center; font-size: 15px;">
                                            <span style="background: #f0f4f8; padding: 6px 12px; border-radius: 8px; display: inline-block;">{{ $partData['total_pcs'] }}</span>
                                        </td>
                                        <td style="padding: 16px 20px; text-align: center;">
                                            <button type="button" class="btn btn-sm js-detail-part"
                                                    data-part-number="{{ $partData['part_number'] }}"
                                                    data-total-box="{{ $partData['total_box'] }}"
                                                    data-total-pcs="{{ $partData['total_pcs'] }}"
                                                    data-pallet-count="{{ $partData['items']->count() }}"
                                                    style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                                                           color: white; 
                                                           border: none; 
                                                           border-radius: 8px;
                                                           padding: 8px 16px;
                                                           font-weight: 600;
                                                           font-size: 13px;
                                                           transition: all 0.3s ease;
                                                           box-shadow: 0 4px 8px rgba(12, 119, 121, 0.15);">
                                                <i class="bi bi-eye"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @elseif($viewMode === 'pallet')
                                @foreach($groupedByPallet as $palletData)
                                    <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 600;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span style="background: #fff8e1; color: #b45309; padding: 6px 12px; border-radius: 8px; font-size: 13px;">
                                                    {{ $palletData['pallet_number'] }}
                                                </span>
                                                @if($palletData['is_merged'])
                                                    <span style="background: #fce7f3; color: #be185d; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;" title="Pallet hasil merge">
                                                        <i class="bi bi-arrow-repeat"></i> M
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td style="padding: 16px 20px; color: #4b5563; font-size: 14px;">
                                            <i class="bi bi-geo-alt me-1"></i> {{ $palletData['location'] }}
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 700; text-align: center; font-size: 15px;">
                                            {{ (int) $palletData['total_box'] }}
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 700; text-align: center; font-size: 15px;">
                                            {{ $palletData['total_pcs'] }}
                                        </td>
                                        <td style="padding: 16px 20px; text-align: center;">
                                            <button type="button" class="btn btn-sm js-detail-pallet"
                                               data-pallet-id="{{ $palletData['pallet_id'] }}"
                                               style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                                                      color: white; 
                                                      border: none; 
                                                      border-radius: 8px;
                                                      padding: 8px 16px;
                                                      font-weight: 600;
                                                      font-size: 13px;
                                                      transition: all 0.3s ease;
                                                      display: inline-block;">
                                                <i class="bi bi-eye"></i> Lihat Isi
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                @foreach($notFullBoxes as $box)
                                    <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                        <td style="padding: 16px 20px; color: #9a3412; font-weight: 700;">
                                            <span style="background: #fff7ed; padding: 6px 12px; border-radius: 8px; display: inline-block;">
                                                {{ $box['box_number'] }}
                                            </span>
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937; font-weight: 600;">
                                            {{ $box['part_number'] }}
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937;">
                                            {{ $box['pallet_number'] }}
                                        </td>
                                        <td style="padding: 16px 20px; color: #1f2937; text-align: center; font-weight: 700;">
                                            {{ $box['pcs_quantity'] }}
                                        </td>
                                        <td style="padding: 16px 20px; color: #4b5563;">
                                            {{ $box['location'] }}
                                        </td>
                                        <td style="padding: 16px 20px; color: #6b7280;">
                                            {{ $box['created_at']->format('d M Y H:i') }}
                                        </td>
                                        <td style="padding: 16px 20px; text-align: center;">
                                            <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                                <button type="button" class="btn btn-sm js-detail-pallet"
                                                   data-pallet-id="{{ $box['pallet_id'] }}"
                                                   style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                                                          color: white; 
                                                          border: none; 
                                                          border-radius: 8px;
                                                          padding: 8px 12px;
                                                          font-weight: 600;
                                                          font-size: 12px;">
                                                    <i class="bi bi-eye"></i> Detail
                                                </button>
                                                @if(!empty($box['box_id']))
                                                    @if(in_array(Auth::user()->role, ['admin_warehouse', 'admin']))
                                                        <button type="button" class="btn btn-sm btn-outline-primary js-edit-box"
                                                                data-box-id="{{ $box['box_id'] }}"
                                                                data-box-number="{{ $box['box_number'] }}"
                                                                data-part-number="{{ $box['part_number'] }}"
                                                                data-pcs-quantity="{{ $box['pcs_quantity'] }}"
                                                                data-stored-at="{{ $box['created_at']->format('Y-m-d H:i:s') }}">
                                                            Edit
                                                        </button>
                                                    @endif
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-box-history" data-box-id="{{ $box['box_id'] }}">
                                                        History
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-5 text-center">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #e5e7eb;"></i>
                    <h5 class="mt-4 text-muted" style="font-weight: 600;">
                        @if($search)
                            Tidak ada stok dengan No Part "<strong>{{ $search }}</strong>"
                        @else
                            Belum ada stok tersimpan
                        @endif
                    </h5>
                    <p class="text-muted">Mulai dengan melakukan input stok untuk melihat data di sini</p>
                </div>
            </div>
        @endif
    </div>
</div>

@include('shared.stock-view.partials.modals')
@include('shared.stock-view.partials.bootstrap-data')
@endsection

@section('scripts')
@include('shared.stock-view.partials.scripts')
@endsection
