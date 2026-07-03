@extends('errors.layout')

@section('title', 'Erro interno')
@section('code', '500')
@section('heading', 'Algo correu mal')
@section('message', config('errors.user_message'))
