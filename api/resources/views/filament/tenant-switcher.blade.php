@php
    $marcas = \App\Models\Tenant::query()->orderBy('name')->get(['slug', 'name']);
    $atual = session('tenant_filter');
@endphp
<div style="margin-right:12px;display:flex;align-items:center;gap:6px;font-size:13px">
    <span>Marca:</span>
    <select onchange="if(this.value){ window.location = '{{ url('/admin/tenant-filter') }}/' + this.value }" style="border:1px solid #ccc;border-radius:6px;padding:3px 6px;background:transparent">
        <option value="todas" @selected(! $atual)>Todas as marcas</option>
        @foreach ($marcas as $m)
            <option value="{{ $m->slug }}" @selected($atual === $m->slug)>{{ $m->name }}</option>
        @endforeach
    </select>
</div>
