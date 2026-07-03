@extends('errors.layout')

@section('title', 'Sessão expirada')
@section('code', '419')
@section('heading', 'Sessão expirada')
@section('message', \App\Support\FriendlyErrorMessages::CSRF_SESSION_EXPIRED)
