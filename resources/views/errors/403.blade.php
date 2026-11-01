@extends('errors.layout')
@section('code', '403')
@section('title', 'Tidak boleh masuk')
@section('message'){{ $exception->getMessage() ?: 'Kamu tidak punya akses ke halaman ini.' }}@endsection
