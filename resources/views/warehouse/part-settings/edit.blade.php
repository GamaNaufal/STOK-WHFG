@extends('shared.layouts.app')

@section('title', 'Edit No Part - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-4"><i class="bi bi-pencil-square"></i> Edit No Part</h1>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('part-settings.update', $partSetting) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">No Part</label>
                    <input type="text" name="part_number" class="form-control" value="{{ old('part_number', $partSetting->part_number) }}" required>
                    @error('part_number')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Qty Box (Tetap)</label>
                    <input type="number" name="qty_box" class="form-control" value="{{ old('qty_box', $partSetting->qty_box) }}" min="1" max="4294967295" required>
                    @error('qty_box')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('part-settings.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
