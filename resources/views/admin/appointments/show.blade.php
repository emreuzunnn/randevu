@extends('admin.layout', ['title' => 'Randevu Detayi'])

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-2xl font-semibold">{{ $appointment->first_name }} {{ $appointment->last_name }}</h2>
            <div class="mt-6 grid gap-4 text-sm text-stone-600">
                <div><span class="font-medium text-stone-900">Randevu Tipi:</span> {{ $appointment->appointment_type }}</div>
                <div><span class="font-medium text-stone-900">Tarih:</span> {{ optional($appointment->appointment_at)->format('d.m.Y') }}</div>
                <div><span class="font-medium text-stone-900">Saat:</span> {{ optional($appointment->appointment_at)->format('H:i') }}</div>
                <div><span class="font-medium text-stone-900">Yer:</span> {{ $appointment->place ?: '-' }}</div>
                <div><span class="font-medium text-stone-900">Durum:</span> {{ $appointment->status }}</div>
                <div><span class="font-medium text-stone-900">Studio:</span> {{ $appointment->studio?->name }}</div>
                <div><span class="font-medium text-stone-900">Oda No:</span> {{ $appointment->room_number ?: '-' }}</div>
                <div><span class="font-medium text-stone-900">Telefon:</span> {{ $appointment->phone_number ?: '-' }}</div>
                <div><span class="font-medium text-stone-900">Pax:</span> {{ $appointment->pax }}</div>
            </div>
        </section>
        <section class="rounded-3xl bg-stone-950 p-6 text-white shadow-sm">
            <h2 class="text-xl font-semibold">Ekip Bilgisi</h2>
            <div class="mt-6 space-y-4 text-sm">
                <div>
                    <div class="text-stone-400">Randevuyu alan</div>
                    <div class="mt-1 font-medium">{{ trim(($appointment->createdBy?->name ?? '').' '.($appointment->createdBy?->surname ?? '')) ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-stone-400">Surucu</div>
                    <div class="mt-1 font-medium">{{ trim(($appointment->assignedDriver?->name ?? '').' '.($appointment->assignedDriver?->surname ?? '')) ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-stone-400">Not</div>
                    <div class="mt-1 font-medium">{{ $appointment->notes ?: '-' }}</div>
                </div>
            </div>
        </section>
    </div>
@endsection
