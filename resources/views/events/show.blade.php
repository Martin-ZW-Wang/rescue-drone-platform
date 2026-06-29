@extends('layouts.app')

@section('content')
    <h1 class="page-title">事件詳情 #{{ $event->id }}</h1>
    <p class="page-subtitle">查看單筆待救援事件的完整資料</p>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Event Detail</h2>
            </div>
        </div>

        <div class="panel-body">
            <p><strong>事件類型：</strong>{{ $event->event_type }}</p>
            <p><strong>TID：</strong>{{ $event->tid }}</p>
            <p><strong>狀態：</strong>{{ $event->status }}</p>
            <p><strong>信心值：</strong>{{ $event->conf }}</p>
            <p><strong>訊息：</strong>{{ $event->message }}</p>
            <p><strong>事件時間：</strong>{{ optional($event->event_time)->format('Y-m-d H:i:s') }}</p>
            <p><strong>Review Status：</strong>{{ $event->review_status }}</p>
            <p><strong>Review Note：</strong>{{ $event->review_note }}</p>

            <hr style="border-color:#334155; margin:18px 0;">

            <p><strong>bbox_json：</strong></p>
            <pre>{{ json_encode($event->bbox_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

            <p><strong>dbg_json：</strong></p>
            <pre>{{ json_encode($event->dbg_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
@endsection