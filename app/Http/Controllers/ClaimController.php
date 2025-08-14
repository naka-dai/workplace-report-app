<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SalesforceClient;
use Illuminate\Support\Carbon;

class ClaimController extends Controller
{
    public function create()
    {
        $maxMb = 5; // 5MB
        return view('claims.create', compact('maxMb'));
    }

    public function store(Request $req, SalesforceClient $sf)
    {
        // 1) 入力検証
        $v = $req->validate([
            'occurred_at'       => ['required','date_format:Y-m-d'], // 発生日
            'severity'          => ['nullable','string','max:10'],   // 重大度（選択リスト）
            'reporting_workplace' => ['nullable','string','max:255'], // 報告職場
            'reporter'          => ['nullable','string','max:30'],   // 報告者
            'target_equipment_name' => ['nullable','string','max:255'], // 対象装置名
            'destination_seiban' => ['nullable','string','max:255'], // 向先製番
            'lot_serial'        => ['nullable','string','max:255'],
            'qrcode'            => ['nullable','string','max:255'],
            'description'       => ['nullable','string','max:4000'], // 不具合内容
            'photo'             => ['nullable','array'],
            'photo.*'           => ['max:20480'],   // 写真 (20MBまで)
        ]);

        // 2) 値の整形（Salesforce へ渡す形式に合わせる）
        // 日付時刻：Salesforce の Date（ISO8601）へ
        $sfOccurred = Carbon::parse($v['occurred_at'])->format('Y-m-d');

        // 重大度：Salesforce 選択リスト値とのマッピング（必要に応じて調整）
        $map = [
            '軽' => '軽',
            '中' => '中',
            '重' => '重',
            '軽微' => '軽', // 追加：フォームからの「軽微」をSalesforceの「軽」にマッピング
        ];
        $sfSeverity = $map[$v['severity']] ?? $v['severity'];

        // 3) Salesforce API に渡すペイロード（Claim__c）
        $payload = [
            'ClaimOccurrenceDate__c' => $sfOccurred,              // クレーム発生日
            'Lightweightlevel__c'    => $sfSeverity,              // 軽重レベル
            'ReportingWorkplace__c'  => $v['reporting_workplace'] ?? null, // 報告職場
            'Reporter__c'            => $v['reporter'] ?? null,   // 報告者
            'OfficeName__c'          => $v['target_equipment_name'] ?? null, // 対象装置名
            'SEIBAN_TEXT__c'         => $v['destination_seiban'] ?? null, // 向先製番
            'LotSerial__c'           => $v['lot_serial'] ?? null,
            'QRCode__c'              => $v['qrcode'] ?? null,
            'Overview__c'            => $v['description'],           // 不具合内容
            'ClaimData__c'           => 'a00fQ000004MkQ5QAK',      // ★固定の関連レコードID
        ];

        // null を除去（空フィールドは送らない）
        $payload = array_filter($payload, fn($x) => !is_null($x) && $x !== '');

        // 写真ファイルがあれば準備
        $filesToUpload = [];
        if ($req->hasFile('photo')) {
            foreach ($req->file('photo') as $uploadedFile) {
                if ($uploadedFile && $uploadedFile->isValid()) {
                    $filesToUpload[] = [
                        'filename' => $uploadedFile->getClientOriginalName(),
                        'mime'     => $uploadedFile->getMimeType(),
                        'contents' => $uploadedFile->get(),
                    ];
                }
            }
        }

        // 4) Salesforce 登録
        try {
            $res = $sf->createClaim($payload, $filesToUpload); // ← 既存のメソッドを利用
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['api' => 'Salesforce登録でエラーが発生しました：'.$e->getMessage()])
                         ->withInput();
        }

        return redirect()->route('claims.create')->with('status', '登録しました（Id: '.$res.')');
    }
}
