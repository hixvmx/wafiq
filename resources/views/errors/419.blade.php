@extends('errors.layout')

@section('code', '419')
@section('title', 'انتهت صلاحية الصفحة')
@section('message', 'انتهت مدة الجلسة لحماية حسابك. أعد تحميل الصفحة ثم حاول مرة أخرى.')
@section('action', 'إعادة المحاولة')
@section('action_url', url()->previous())
