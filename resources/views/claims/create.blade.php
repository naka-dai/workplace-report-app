@extends('layout')

@section('content')
  <h2 style="margin-top:0">不具合報告</h2>

  @if ($errors->any())
    <div class="err">
      <div><strong>入力に誤りがあります。</strong></div>
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  <form method="post" action="{{ route('claims.store') }}" enctype="multipart/form-data">
    @csrf

    <label>発生日</label>
    <input type="date" name="occurred_at" value="{{ old('occurred_at') ?: now()->format('Y-m-d') }}" required>

    <label>重大度</label>
    <select name="severity">
      @foreach (['軽','中','重'] as $opt)
        <option value="{{ $opt }}" @selected(old('severity')===$opt)>{{ $opt }}</option>
      @endforeach
    </select>

    <label>報告職場</label>
    <select name="reporting_workplace" id="reporting_workplace_select">
      <option value="">選択してください</option>
      @foreach (['E39_塗装','3807_出荷','G56_資材','3120_材料_加工1G','3104_材料_加工2G'] as $opt)
        <option value="{{ $opt }}" @selected(old('reporting_workplace')===$opt)>{{ $opt }}</option>
      @endforeach
    </select>

    <label>報告者</label>
    <input type="text" name="reporter" id="reporter_input" value="{{ old('reporter') }}">

    <label>対象装置名</label>
    <input type="text" name="target_equipment_name" value="{{ old('target_equipment_name') }}">

    <label>向先製番</label>
    <input type="text" name="destination_seiban" value="{{ old('destination_seiban') }}">

    <label>ロット/シリアル（任意）</label>
    <input type="text" name="lot_serial" value="{{ old('lot_serial') }}">

    <label>QR/バーコード（任意）</label>
    <input type="text" name="qrcode" value="{{ old('qrcode') }}">

    <label>不具合内容</label>
    <textarea name="description">{{ old('description') }}</textarea>

    <label>写真（最大 {{ $maxMb }}MB／jpg・png・heic）</label>
    <input type="file" name="photo[]" multiple>

    <div class="actions">
      <button type="submit">送信</button>
    </div>
  </form>

  <p class="muted" style="margin-top:12px">
    ※ 登録後、Salesforce のカスタムオブジェクト（Defect_Report__c）にレコードが作成され、写真は Files として紐づきます。
  </p>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const reportingWorkplaceSelect = document.getElementById('reporting_workplace_select');
      const reporterInput = document.getElementById('reporter_input');

      // Load saved values
      const savedReportingWorkplace = localStorage.getItem('reporting_workplace');
      if (savedReportingWorkplace) {
        reportingWorkplaceSelect.value = savedReportingWorkplace;
      }

      const savedReporter = localStorage.getItem('reporter');
      if (savedReporter) {
        reporterInput.value = savedReporter;
      }

      // Save values on change
      reportingWorkplaceSelect.addEventListener('change', function() {
        localStorage.setItem('reporting_workplace', this.value);
      });

      reporterInput.addEventListener('input', function() {
        localStorage.setItem('reporter', this.value);
      });
    });
  </script>
@endsection
