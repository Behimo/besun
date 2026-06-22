@php
$configData = Helper::appClasses();
$isFront = true;
@endphp

@section('layoutContent')

@extends('layouts.vuexy.commonMaster' )

@include('layouts.vuexy.sections.navbar/navbar-front')

<!-- Sections:Start -->
@yield('content')
<!-- / Sections:End -->

@include('layouts.vuexy.sections.footer/footer-front')
@endsection
