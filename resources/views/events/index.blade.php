@extends('layouts.app')

@section('content')
    <h1 class="page-title">事件列表</h1>
    <p class="page-subtitle">顯示待救援事件、狀態與人工覆核資料</p>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Rescue Events</h2>
            </div>
        </div>

        <div class="panel-body">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>事件類型</th>
                    <th>狀態</th>
                    <th>信心值</th>
                    <th>時間</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $event->id }}</td>
                        <td>{{ $event->event_type }}</td>
                        <td>{{ $event->status }}</td>
                        <td>{{ $event->conf }}</td>
                        <td>{{ optional($event->event_time)->format('Y-m-d H:i:s') }}</td>
                        <td>
                            <a class="link" href="{{ route('events.show', $event) }}">查看</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">目前尚無事件資料</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div style="margin-top: 16px;">
                {{ $events->links() }}
            </div>
        </div>
    </div>
@endsection