@php
$configData = Helper::appClasses();
$configData['hasCustomizer'] = false;
$contentNavbar = true;
$containerNav = 'container-xxl';
$isNavbar = true;
$isMenu = true;
$isFlex = false;
$isFooter = true;
$navbarDetached = 'navbar-detached';
$menuFixed = $configData['menuFixed'] ?? true;
$navbarType = $configData['navbarType'] ?? '';
$footerFixed = $configData['footerFixed'] ?? false;
$menuCollapsed = $configData['menuCollapsed'] ?? false;
$container = ($configData['contentLayout'] ?? 'compact') === 'compact' ? 'container-xxl' : 'container-fluid';
@endphp
@extends('layouts.vuexy.commonMaster')

@section('layoutContent')
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    @include('layouts.vuexy.sections.menu.verticalMenu')

    <div class="layout-page">
      @include('layouts.vuexy.sections.navbar.navbar-admin')

      <div class="content-wrapper">
        <div class="{{ $container }} flex-grow-1 container-p-y">
          @if (session('success'))
            <div class="alert alert-success alert-dismissible" role="alert">
              {{ session('success') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
            </div>
          @endif
          @if (session('error'))
            <div class="alert alert-danger alert-dismissible" role="alert">
              {{ session('error') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
            </div>
          @endif
          @yield('content')
        </div>

        @if ($isFooter)
          @include('layouts.vuexy.sections.footer.footer-admin')
        @endif
        <div class="content-backdrop fade"></div>
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
    <div class="drag-target"></div>
  </div>
</div>
@endsection
