@extends('admin.layout', ['title' => 'Eski Müşteriler'])

@section('content')
    @php
        abort_unless(auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]), 403);
    @endphp
    <div data-admin-old-customers></div>
@endsection
