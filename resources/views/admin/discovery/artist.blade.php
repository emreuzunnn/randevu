@extends('admin.layout', ['title' => 'Artist Detayı'])

@section('content')
    <div data-admin-public-artist-detail data-artist-id="{{ request()->route('user') }}"></div>
@endsection
