@extends('layouts.school')

@section('title', 'Order Cards')
@section('heading', 'Order ID Cards')
@section('subheading', 'Select students — Arkeon prints and delivers')

@section('content')

@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ $errors->first() }}</div>
@endif

{{-- Class filter --}}
<form method="GET" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-5 flex flex-wrap items-center gap-3">
    <label class="text-sm text-gray-600">Filter by class:</label>
    <select name="class_id" onchange="this.form.submit()" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
        <option value="">All classes</option>
        @foreach($classes as $c)
            <option value="{{ $c->id }}" {{ (string)$classId === (string)$c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
        @endforeach
    </select>
    @if($classId)
        <a href="{{ route('school.card-orders.create') }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
    @endif
</form>

@if($roster->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>No active students{{ $classId ? ' in this class' : '' }}.</p>
    </div>
@else
<form method="POST" action="{{ route('school.card-orders.store') }}">
    @csrf

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Order Type</label>
                <select name="order_type" id="order_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                    @foreach($orderTypes as $type)
                        <option value="{{ $type }}" data-price="{{ $pricing[$type] }}">
                            {{ ucfirst(str_replace('_',' ',$type)) }} — {{ $pricing[$type] }} ETB/card
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="text-sm text-gray-500">
                <span id="selected-count">0</span> selected ·
                Estimated total: <span id="estimated-total" class="font-semibold text-gray-900">0 ETB</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <p class="font-medium text-gray-900">{{ $roster->count() }} students</p>
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600">
                Select all
            </label>
        </div>
        <div class="divide-y divide-gray-50 max-h-[28rem] overflow-y-auto">
            @foreach($roster as $student)
                @php $inOpen = in_array($student->id, $alreadyOrdered, true); @endphp
                <label class="px-5 py-3 flex items-center gap-3 cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="student-cb rounded border-gray-300 text-indigo-600">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500 flex-shrink-0">
                        {{ strtoupper(substr($student->first_name,0,1)) }}{{ strtoupper(substr($student->last_name,0,1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ $student->full_name }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $student->uid }} · {{ $student->schoolClass?->display_name ?? 'No class' }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if(!$student->hasPhoto())
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-600" title="No photo — card prints with a placeholder">No photo</span>
                        @endif
                        @if($inOpen)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-500" title="Already in an open order">In open order</span>
                        @endif
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">Create Order</button>
        <a href="{{ route('school.card-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>
</form>

<script>
    (function () {
        var selectAll = document.getElementById('select-all');
        var boxes     = Array.prototype.slice.call(document.querySelectorAll('.student-cb'));
        var typeSel   = document.getElementById('order_type');
        var countEl   = document.getElementById('selected-count');
        var totalEl   = document.getElementById('estimated-total');

        function unitPrice() {
            var opt = typeSel.options[typeSel.selectedIndex];
            return parseInt(opt.getAttribute('data-price'), 10) || 0;
        }
        function refresh() {
            var n = boxes.filter(function (b) { return b.checked; }).length;
            countEl.textContent = n;
            totalEl.textContent = (n * unitPrice()).toLocaleString() + ' ETB';
        }
        selectAll.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = selectAll.checked; });
            refresh();
        });
        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
        typeSel.addEventListener('change', refresh);
        refresh();
    })();
</script>
@endif
@endsection
