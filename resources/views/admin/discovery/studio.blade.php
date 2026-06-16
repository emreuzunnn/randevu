@extends('admin.layout', ['title' => 'Stüdyo Detayı'])

@section('content')
    <div data-admin-public-studio-detail data-studio-id="{{ request()->route('studio') }}"></div>
@endsection
