<?php

namespace App\Support;

use Illuminate\Http\Request;

class ReportPageResponse
{
    public static function make(Request $request, string $view, array $data)
    {
        $name = substr($view, strlen('operator.reports.'));
        $title = match ($name) {
            'stock-input' => 'Laporan Input Stok - Warehouse FG Yamato',
            'withdrawal' => 'Laporan Pengambilan Stok - Warehouse FG Yamato',
            'record-detail' => 'Rincian Laporan',
            default => 'Laporan Operasional',
        };
        $response = $request->expectsJson()
            ? response()->json(['html' => view('operator.reports.partials.'.$name, $data)->render(),
                'title' => $title, 'url' => $request->fullUrl()])
            : response()->view($view, $data);
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
    }
}
