@extends('admin.layout', ['title' => 'Dashboard'])

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl bg-white p-5 shadow-sm"><div class="text-sm text-stone-500">Toplam Randevu</div><div class="mt-3 text-4xl font-semibold">{{ $summary['total_appointments'] }}</div></div>
        <div class="rounded-3xl bg-white p-5 shadow-sm"><div class="text-sm text-stone-500">Iptal Sayisi</div><div class="mt-3 text-4xl font-semibold">{{ $summary['cancelled_appointments'] }}</div></div>
        <div class="rounded-3xl bg-white p-5 shadow-sm"><div class="text-sm text-stone-500">Calisan Sayisi</div><div class="mt-3 text-4xl font-semibold">{{ $summary['employee_count'] }}</div></div>
        <div class="rounded-3xl bg-white p-5 shadow-sm"><div class="text-sm text-stone-500">Transfer Sayisi</div><div class="mt-3 text-4xl font-semibold">{{ $summary['transfer_count'] }}</div></div>
    </div>

    <div class="mt-8 grid gap-4 lg:grid-cols-3">
        @foreach ($reports as $report)
            <section class="rounded-3xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-stone-500">{{ $report['label'] }} Rapor</div>
                        <div class="mt-1 text-xs text-stone-400">{{ $report['date_from'] }} - {{ $report['date_to'] }}</div>
                    </div>
                    <div class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">Rapor</div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-2xl bg-stone-50 p-4">
                        <div class="text-stone-500">Toplam</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $report['total_appointments'] }}</div>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 p-4">
                        <div class="text-emerald-700">Tamamlandi</div>
                        <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ $report['completed_appointments'] }}</div>
                    </div>
                    <div class="rounded-2xl bg-rose-50 p-4">
                        <div class="text-rose-700">Iptal</div>
                        <div class="mt-2 text-2xl font-semibold text-rose-900">{{ $report['cancelled_appointments'] }}</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 p-4">
                        <div class="text-amber-700">Bekleyen</div>
                        <div class="mt-2 text-2xl font-semibold text-amber-900">{{ $report['pending_appointments'] }}</div>
                    </div>
                </div>
            </section>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Studyo Detaylari</h2>
            <div class="mt-5 overflow-hidden rounded-2xl border border-stone-200">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-3 text-left">Studyo</th>
                            <th class="px-4 py-3 text-left">Yer</th>
                            <th class="px-4 py-3 text-left">Aktif Personel</th>
                            <th class="px-4 py-3 text-left">Randevu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 bg-white">
                        @foreach ($studios as $studio)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $studio->name }}</td>
                                <td class="px-4 py-3">
                                    <div>{{ $studio->location ?: '-' }}</div>
                                    <div class="text-xs text-stone-400">{{ $studio->shop?->name ?: 'Dukkan yok' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $studio->active_staff_count }}</td>
                                <td class="px-4 py-3">{{ $studio->appointments_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="rounded-3xl bg-stone-950 p-6 text-white shadow-sm">
            <h2 class="text-xl font-semibold">Son Randevular</h2>
            <div class="mt-5 space-y-3">
                @foreach ($recentAppointments as $appointment)
                    <a href="{{ route('admin.appointments.show', $appointment) }}" class="block rounded-2xl bg-white/5 p-4 transition hover:bg-white/10">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-medium">{{ $appointment->first_name }} {{ $appointment->last_name }}</div>
                                <div class="mt-1 text-sm text-stone-300">{{ $appointment->studio?->name }} • {{ optional($appointment->appointment_at)->format('d.m.Y H:i') }}</div>
                            </div>
                            <span class="rounded-full bg-orange-400/20 px-3 py-1 text-xs uppercase tracking-wide text-orange-200">{{ $appointment->status }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
@endsection
