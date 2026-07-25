@extends('admin.layout', ['title' => 'Otel Ciroları'])

@section('content')
    @php
        abort_unless(auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]), 403);
    @endphp
    <div data-admin-hotel-revenues></div>
@endsection
