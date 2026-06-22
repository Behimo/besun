@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@php
$configData = Helper::appClasses();
@endphp

@isset($configData["layout"])
@include((( $configData["layout"] === 'horizontal') ? 'layouts.vuexy.horizontalLayout' :
(( $configData["layout"] === 'blank') ? 'layouts.vuexy.blankLayout' :
(($configData["layout"] === 'front') ? 'layouts.vuexy.layoutFront' : 'layouts.vuexy.contentNavbarLayout') )))
@endisset
