@extends('shared.layouts.app')

@section('title', 'Tambah Box')

@section('content')
<div class="mb-4">
    <h4 class="mb-1">Tambah Box</h4>
    <p class="text-muted mb-0">Input data box baru</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('boxes.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Box Number</label>
                    <input type="text" name="box_number" class="form-control" value="{{ old('box_number') }}" required>
                    @error('box_number')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Part Number</label>
                    <input type="text" name="part_number" class="form-control" value="{{ old('part_number') }}" required>
                    @error('part_number')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Part Name</label>
                    <input type="text" name="part_name" class="form-control" value="{{ old('part_name') }}">
                    @error('part_name')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">PCS Quantity</label>
                    <input type="number" name="pcs_quantity" class="form-control" min="1" value="{{ old('pcs_quantity') }}" required>
                    @error('pcs_quantity')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Qty Box</label>
                    <input type="number" name="qty_box" class="form-control" min="1" value="{{ old('qty_box') }}">
                    @error('qty_box')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type Box</label>
                    <input type="text" name="type_box" class="form-control" value="{{ old('type_box') }}">
                    @error('type_box')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">WK Transfer</label>
                    <input type="text" name="wk_transfer" class="form-control" value="{{ old('wk_transfer') }}">
                    @error('wk_transfer')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Lot 01</label>
                    <input type="text" name="lot01" class="form-control" value="{{ old('lot01') }}">
                    @error('lot01')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lot 02</label>
                    <input type="text" name="lot02" class="form-control" value="{{ old('lot02') }}">
                    @error('lot02')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lot 03</label>
                    <input type="text" name="lot03" class="form-control" value="{{ old('lot03') }}">
                    @error('lot03')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('boxes.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
