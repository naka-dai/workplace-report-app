<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\SalesforceClient;

class DefectController extends Controller
{
    public function form()
    {
        return view('defect_form', [
            'maxMb' => (int) config('salesforce.max_photo_mb'),
        ]);
    }

    public function submit(Request $req, SalesforceClient $sf)
    {
        $maxMb = (int) config('salesforce.max_photo_mb', 8);
        $maxKb = $maxMb * 1024;

        $validated = $req->validate([
            'occurred_at' => 'required|date',
            'severity'    => 'required|string|max:16',
            'work_center' => 'nullable|string|max:64',
            'product'     => 'nullable|string|max:64',
            'lot_serial'  => 'nullable|string|max:64',
            'qrcode'      => 'nullable|string|max:128',
            'description' => 'required|string|max:4000',
            'photo'       => "nullable|file|mimes:jpg,jpeg,png,heic|max:$maxKb",
        ]);

        $offlineGuid = (string) Str::uuid();

        $defectPayload = [
            'Occurred_At__c'  => date('c', strtotime($validated['occurred_at'])),
            'Severity__c'     => $validated['severity'],
            'WorkCenter__c'   => $validated['work_center'] ?? null,
            'Product__c'      => $validated['product'] ?? null,
            'LotOrSerial__c'  => $validated['lot_serial'] ?? null,
            'QROrBarcode__c'  => $validated['qrcode'] ?? null,
            'Description__c'  => $validated['description'],
            'OfflineGUID__c'  => $offlineGuid,
        ];

        // 空値は除外
        $defectPayload = array_filter($defectPayload, fn($v) => !is_null($v) && $v !== '');

        $recordId = $sf->createDefect($defectPayload);

        if ($req->hasFile('photo')) {
            $file = $req->file('photo');
            $sf->uploadFile($recordId, $file->getClientOriginalName(), $file->getClientMimeType(), file_get_contents($file->getRealPath()));
        }

        return redirect()->back()->with('ok', "登録しました（ID: {$recordId}）");
    }
}
