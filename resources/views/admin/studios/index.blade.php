@extends('admin.layout', ['title' => 'Studyolar'])

@section('content')
    <div class="grid gap-6">
        @foreach ($studios as $studio)
            <section class="rounded-3xl bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold">{{ $studio->name }}</h2>
                        <div class="mt-1 text-sm text-stone-500">{{ $studio->shop?->name ?: 'Dukkan yok' }} | {{ $studio->location ?: '-' }}</div>
                    </div>
                    <div class="text-right text-sm text-stone-500">
                        <div>Aktif personel: {{ $studio->active_staff_count }}</div>
                        <div>Randevu: {{ $studio->appointments_count }}</div>
                    </div>
                </div>
                <form action="{{ route('admin.studios.update', $studio) }}" method="POST" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <input name="name" value="{{ $studio->name }}" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Studyo ismi">
                    <input name="location" value="{{ $studio->location }}" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Yer">
                    <input name="logo_path" value="{{ $studio->logo_path }}" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Logo yolu">
                    <div class="flex gap-3">
                        <input name="notification_lead_minutes" value="{{ $studio->notification_lead_minutes }}" type="number" min="0" class="flex-1 rounded-2xl border border-stone-300 px-4 py-3" placeholder="Bildirim dakika">
                        <button class="rounded-2xl bg-stone-900 px-5 py-3 text-sm font-medium text-white">Kaydet</button>
                    </div>
                </form>
            </section>
        @endforeach
    </div>
@endsection
