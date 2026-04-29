@extends('admin.layout', ['title' => 'Kullanicilar'])

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
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
            <div class="overflow-hidden rounded-2xl border border-stone-200">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-3 text-left">Isim</th>
                            <th class="px-4 py-3 text-left">Rol</th>
                            <th class="px-4 py-3 text-left">Durum</th>
                            <th class="px-4 py-3 text-left">Mail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ trim($user->name.' '.$user->surname) }}</td>
                                <td class="px-4 py-3">{{ $user->pivot->role }}</td>
                                <td class="px-4 py-3">{{ $user->pivot->work_status }}</td>
                                <td class="px-4 py-3">{{ $user->email }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Kullanici Ekle</h2>
            <form action="{{ route('admin.users.store') }}" method="POST" class="mt-5 grid gap-4">
                @csrf
                <select name="studio_id" class="rounded-2xl border border-stone-300 px-4 py-3">
                    @foreach ($studios as $studio)
                        <option value="{{ $studio->id }}" @selected($selectedStudio?->id === $studio->id)>{{ $studio->shop?->name ? $studio->shop->name.' / ' : '' }}{{ $studio->name }}</option>
                    @endforeach
                </select>
                <input name="name" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Isim">
                <input name="surname" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Soyad">
                <input name="phone" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Telefon">
                <select name="role" class="rounded-2xl border border-stone-300 px-4 py-3">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                <input name="email" type="email" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Mail">
                <input name="password" type="password" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="6 haneli sifre">
                <input name="password_confirmation" type="password" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Sifre tekrar">
                <button class="rounded-2xl bg-orange-500 px-4 py-3 font-semibold text-stone-950">Kaydet</button>
            </form>
        </section>
    </div>
@endsection
