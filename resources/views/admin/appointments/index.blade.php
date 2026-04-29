@extends('admin.layout', ['title' => 'Randevular'])

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <form method="GET" class="mb-5 flex flex-wrap items-end gap-4">
                <div>
                    <label class="mb-2 block text-sm text-stone-500">Studyo Sec</label>
                    <select name="studio_id" class="rounded-2xl border border-stone-300 px-4 py-3">
                        @foreach ($studios as $studio)
                            <option value="{{ $studio->id }}" @selected($selectedStudio?->id === $studio->id)>{{ $studio->shop?->name ? $studio->shop->name.' / ' : '' }}{{ $studio->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="rounded-2xl bg-stone-900 px-4 py-3 text-sm font-medium text-white">Listele</button>
            </form>
            <div class="space-y-3">
                @foreach ($appointments as $appointment)
                    <a href="{{ route('admin.appointments.show', $appointment) }}" class="block rounded-2xl border border-stone-200 p-4 transition hover:border-orange-300 hover:bg-orange-50/40">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-semibold">{{ $appointment->first_name }} {{ $appointment->last_name }}</div>
                                <div class="mt-1 text-sm text-stone-500">{{ optional($appointment->appointment_at)->format('d.m.Y H:i') }} • {{ $appointment->place ?: '-' }}</div>
                            </div>
                            <span class="rounded-full bg-stone-900 px-3 py-1 text-xs uppercase tracking-wide text-white">{{ $appointment->status }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Randevu Ekle</h2>
            <form action="{{ route('admin.appointments.store') }}" method="POST" class="mt-5 grid gap-4">
                @csrf
                <select name="studio_id" class="rounded-2xl border border-stone-300 px-4 py-3">
                    @foreach ($studios as $studio)
                        <option value="{{ $studio->id }}" @selected($selectedStudio?->id === $studio->id)>{{ $studio->shop?->name ? $studio->shop->name.' / ' : '' }}{{ $studio->name }}</option>
                    @endforeach
                </select>
                <input name="slip_image_path" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Fis / Gorsel yolu">
                <div class="grid gap-4 md:grid-cols-2">
                    <input name="first_name" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Ad">
                    <input name="last_name" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Soyad">
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <input name="phone" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Telefon">
                    <input name="room_number" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Oda No">
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <input name="pax" type="number" min="1" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Kisi sayisi">
                    <input name="appointment_type" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Randevu tipi">
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <input name="date" type="date" class="rounded-2xl border border-stone-300 px-4 py-3">
                    <input name="time" type="time" class="rounded-2xl border border-stone-300 px-4 py-3">
                </div>
                <input name="place" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Yer">
                <select name="assigned_driver_user_id" class="rounded-2xl border border-stone-300 px-4 py-3">
                    <option value="">Surucu sec</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ trim($driver->name.' '.$driver->surname) }}</option>
                    @endforeach
                </select>
                <textarea name="notes" rows="3" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Not"></textarea>
                <button class="rounded-2xl bg-orange-500 px-4 py-3 font-semibold text-stone-950">Randevu Kaydet</button>
            </form>
        </section>
    </div>
@endsection
