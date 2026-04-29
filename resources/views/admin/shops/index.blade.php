@extends('admin.layout', ['title' => 'Dukkanlar'])

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Dukkan Listesi</h2>
            <div class="mt-5 grid gap-4">
                @foreach ($shops as $shop)
                    <div class="rounded-2xl border border-stone-200 p-5">
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <div class="text-lg font-semibold">{{ $shop->name }}</div>
                                <div class="mt-1 text-sm text-stone-500">{{ $shop->location ?: '-' }}</div>
                                <div class="mt-2 text-sm text-stone-500">
                                    Yonetici: {{ $shop->manager?->fullName() ?: '-' }} | Studyo: {{ $shop->studios_count }}
                                </div>
                            </div>
                        </div>
                        <form action="{{ route('admin.shops.update', $shop) }}" method="POST" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @csrf
                            <input name="name" value="{{ $shop->name }}" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Dukkan adi">
                            <input name="location" value="{{ $shop->location }}" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Konum">
                            @if (auth()->user()?->hasRole(\App\Enums\UserRole::Admin))
                                <select name="manager_user_id" class="rounded-2xl border border-stone-300 px-4 py-3">
                                    <option value="">Yonetici sec</option>
                                    @foreach ($managers as $manager)
                                        <option value="{{ $manager->id }}" @selected($shop->manager_user_id === $manager->id)>{{ $manager->fullName() }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <button class="rounded-2xl bg-stone-900 px-5 py-3 text-sm font-medium text-white">Kaydet</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
        @if (auth()->user()?->hasRole(\App\Enums\UserRole::Admin))
            <section class="rounded-3xl bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Dukkan Ekle</h2>
                <form action="{{ route('admin.shops.store') }}" method="POST" class="mt-5 grid gap-4">
                    @csrf
                    <input name="name" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Dukkan adi">
                    <input name="location" class="rounded-2xl border border-stone-300 px-4 py-3" placeholder="Konum">
                    <select name="manager_user_id" class="rounded-2xl border border-stone-300 px-4 py-3">
                        <option value="">Yonetici sec</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->fullName() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-2xl bg-orange-500 px-4 py-3 font-semibold text-stone-950">Olustur</button>
                </form>
            </section>
        @endif
    </div>
@endsection
